<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

use App\Domain\Asset\Models\Asset;
use App\Domain\BoatMake\Models\BoatMake;
use App\Support\OpenAi\OpenAiModelResolver;
use App\Support\OpenAi\OpenAiRequestType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class InvoiceCatalogMappingService
{
    private const CONFIDENCE_THRESHOLD = 0.5;

    public function __construct(
        private readonly InvoiceBrandCatalogBuilder $catalogBuilder = new InvoiceBrandCatalogBuilder,
    ) {}

    /**
     * Validate AI-selected catalog IDs, enrich display labels, and map any remaining rows.
     *
     * @param  list<array<string, mixed>>  $lineItems
     * @return list<array<string, mixed>>
     */
    public function apply(BoatMake $brand, array $lineItems): array
    {
        $catalog = $this->catalogBuilder->build($brand);
        $assetsById = $this->assetsById($brand);

        if ($catalog === []) {
            return array_map(
                fn (array $row) => $this->attachMapping($row, null, null, null, null, 'unmatched', 0.0, false),
                $lineItems,
            );
        }

        $enriched = [];
        $needsAiMapping = [];

        foreach ($lineItems as $row) {
            $assetId = isset($row['asset_id']) ? (int) $row['asset_id'] : null;
            if ($assetId) {
                $confidence = (float) ($row['mapping_confidence'] ?? 0.8);
                $variantId = isset($row['asset_variant_id']) ? (int) $row['asset_variant_id'] : null;
                $enriched[] = $this->resolveMapping(
                    $row,
                    $assetId,
                    $variantId > 0 ? $variantId : null,
                    $confidence,
                    $assetsById,
                );
            } else {
                $needsAiMapping[] = $row;
            }
        }

        if ($needsAiMapping !== []) {
            $aiMapped = $this->mapWithAi($brand, $needsAiMapping, $catalog, $assetsById);
            foreach ($aiMapped as $row) {
                $enriched[] = $row;
            }
        }

        usort($enriched, fn (array $a, array $b) => ($a['row_index'] ?? 0) <=> ($b['row_index'] ?? 0));

        return $enriched;
    }

    /**
     * @param  list<array<string, mixed>>  $lineItems
     * @param  list<array<string, mixed>>  $catalog
     * @param  Collection<int, Asset>  $assetsById
     * @return list<array<string, mixed>>
     */
    protected function mapWithAi(BoatMake $brand, array $lineItems, array $catalog, $assetsById): array
    {
        if (! config('openai.api_key') && ! env('OPENAI_API_KEY')) {
            return array_map(
                fn (array $row) => $this->attachMapping($row, null, null, null, null, 'unmatched', 0.0, false),
                $lineItems,
            );
        }

        $model = OpenAiModelResolver::resolve(OpenAiRequestType::DocumentExtract);

        $payload = json_encode([
            'brand' => $brand->display_name,
            'catalog' => $catalog,
            'line_items' => array_map(fn (array $row) => [
                'row_index' => $row['row_index'],
                'item_code' => $row['item_code'] ?? null,
                'description' => $row['description'] ?? null,
                'extracted_model' => $row['extracted_model'] ?? null,
                'extracted_variant' => $row['extracted_variant'] ?? null,
                'hin' => $row['hin'] ?? null,
            ], $lineItems),
        ], JSON_THROW_ON_ERROR);

        try {
            $response = OpenAI::chat()->create(OpenAiModelResolver::sanitizeChatPayload([
                'model' => $model,
                'temperature' => 0,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'invoice_catalog_mapping',
                        'strict' => true,
                        'schema' => $this->responseSchema(),
                    ],
                ],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $payload],
                ],
            ]));
        } catch (\Throwable $e) {
            Log::error('InvoiceCatalogMappingService OpenAI call failed', [
                'boat_make_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            return array_map(
                fn (array $row) => $this->attachMapping($row, null, null, null, null, 'unmatched', 0.0, false),
                $lineItems,
            );
        }

        $content = $response->choices[0]->message->content ?? null;
        if ($content === null || $content === '') {
            return array_map(
                fn (array $row) => $this->attachMapping($row, null, null, null, null, 'unmatched', 0.0, false),
                $lineItems,
            );
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $mappings = collect((array) ($decoded['mappings'] ?? []))->keyBy('row_index');

        $out = [];
        foreach ($lineItems as $row) {
            $mapping = $mappings->get($row['row_index']);
            if (! is_array($mapping)) {
                $out[] = $this->attachMapping($row, null, null, null, null, 'unmatched', 0.0, false);

                continue;
            }

            $assetId = isset($mapping['asset_id']) ? (int) $mapping['asset_id'] : null;
            $variantId = isset($mapping['asset_variant_id']) ? (int) $mapping['asset_variant_id'] : null;
            $confidence = (float) ($mapping['confidence'] ?? 0);

            if (! $assetId) {
                $out[] = $this->attachMapping($row, null, null, null, null, 'unmatched', $confidence, false);

                continue;
            }

            $out[] = $this->resolveMapping($row, $assetId, $variantId > 0 ? $variantId : null, $confidence, $assetsById);
        }

        return $out;
    }

    /**
     * @param  Collection<int, Asset>  $assetsById
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function resolveMapping(
        array $row,
        int $assetId,
        ?int $variantId,
        float $confidence,
        $assetsById,
    ): array {
        $asset = $assetsById->get($assetId);
        if ($asset === null) {
            return $this->attachMapping($row, null, null, null, null, 'unmatched', $confidence, false);
        }

        $assetLabel = (string) $asset->display_name;
        $hasVariants = (bool) $asset->has_variants;
        $variantLabel = null;

        if ($hasVariants) {
            $validVariant = $variantId && $asset->variants->contains('id', $variantId);
            if ($validVariant) {
                $variant = $asset->variants->firstWhere('id', $variantId);
                $variantLabel = $variant ? (string) ($variant->display_name ?: $variant->name) : null;
            }

            if (! $validVariant) {
                return $this->attachMapping(
                    $row,
                    $asset->id,
                    null,
                    $assetLabel,
                    null,
                    'needs_attention',
                    $confidence,
                    true,
                );
            }

            $status = $confidence >= self::CONFIDENCE_THRESHOLD ? 'matched' : 'needs_attention';

            return $this->attachMapping(
                $row,
                $asset->id,
                $variantId,
                $assetLabel,
                $variantLabel,
                $status,
                $confidence,
                true,
            );
        }

        $status = $confidence >= self::CONFIDENCE_THRESHOLD ? 'matched' : 'needs_attention';

        return $this->attachMapping(
            $row,
            $asset->id,
            null,
            $assetLabel,
            null,
            $status,
            $confidence,
            false,
        );
    }

    /**
     * @return Collection<int, Asset>
     */
    protected function assetsById(BoatMake $brand)
    {
        return Asset::query()
            ->where('make_id', $brand->id)
            ->with(['variants' => function ($q) {
                $q->select('id', 'asset_id', 'display_name', 'name')
                    ->where(function ($inner) {
                        $inner->where('inactive', false)->orWhereNull('inactive');
                    });
            }])
            ->get()
            ->keyBy('id');
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You map extracted invoice unit rows to the dealer's asset catalog for this brand.

Rules:
- Pick asset_id only from the provided catalog.
- Match using item_code, description, extracted model hints, size/series/color codes, and variant keys or names.
- When has_variants is true, set asset_variant_id to the best matching variant on that asset.
- When has_variants is false, asset_variant_id must be null.
- confidence: 0.0–1.0 for how sure the match is.
- Return one mapping per row_index in line_items. Use null IDs when no reasonable match exists.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['mappings'],
            'properties' => [
                'mappings' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['row_index', 'asset_id', 'asset_variant_id', 'confidence'],
                        'properties' => [
                            'row_index' => ['type' => 'integer'],
                            'asset_id' => ['type' => ['integer', 'null']],
                            'asset_variant_id' => ['type' => ['integer', 'null']],
                            'confidence' => ['type' => 'number'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function attachMapping(
        array $row,
        ?int $assetId,
        ?int $variantId,
        ?string $assetDisplayName,
        ?string $variantDisplayName,
        string $matchStatus,
        float $confidence,
        bool $assetHasVariants,
    ): array {
        return array_merge($row, [
            'asset_id' => $assetId,
            'asset_variant_id' => $variantId,
            'asset_display_name' => $assetDisplayName,
            'variant_display_name' => $variantDisplayName,
            'asset_has_variants' => $assetHasVariants,
            'match_status' => $matchStatus,
            'confidence' => round($confidence, 2),
            'include' => true,
        ]);
    }
}
