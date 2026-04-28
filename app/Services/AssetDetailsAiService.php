<?php

namespace App\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class AssetDetailsAiService
{
    /** @var list<string> */
    private const STATIC_SPEC_KEYS = ['length', 'width', 'hull_type', 'hull_material', 'boat_type'];

    /**
     * @param  array<string, mixed>  $context
     * @return array{description: ?string, spec_updates: list<array<string, mixed>>, static_updates: list<array<string, mixed>>}
     */
    public function suggest(Asset $asset, array $context): array
    {
        $apiKey = config('openai.api_key') ?? env('OPENAI_API_KEY');
        if (! $apiKey) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $hasVariants = (bool) ($context['has_variants'] ?? false);
        $definitionsById = $this->allowedSpecDefinitions((int) $asset->type);

        $userPayload = [
            'asset_id' => $asset->id,
            'asset_type' => (int) $asset->type,
            'display_name' => $context['display_name'] ?? $asset->display_name,
            'asset_make' => $this->resolveAssetMakeDisplay($asset, $context),
            'description' => $context['description'] ?? $asset->description,
            'has_variants' => $hasVariants,
            'variants' => $context['variants'] ?? [],
            'specs' => $context['specs'] ?? [],
            'static_fields' => $context['static_fields'] ?? [],
        ];

        $model = (string) config('boat_meta_ai.generate_model', 'gpt-4o-mini');

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'asset_details_suggestion',
                        'strict' => false,
                        'schema' => $this->responseSchema(),
                    ],
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($userPayload, JSON_THROW_ON_ERROR),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('AssetDetailsAiService OpenAI call failed', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('AI request failed. Please try again.');
        }

        $content = $response->choices[0]->message->content ?? null;
        if ($content === null || $content === '') {
            throw new \RuntimeException('Empty response from AI.');
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid AI response shape.');
        }

        $description = array_key_exists('description', $decoded)
            ? (is_string($decoded['description']) ? $decoded['description'] : null)
            : null;

        $rawSpecUpdates = is_array($decoded['spec_updates'] ?? null) ? $decoded['spec_updates'] : [];
        $rawStaticUpdates = is_array($decoded['static_updates'] ?? null) ? $decoded['static_updates'] : [];

        if ($hasVariants) {
            $specUpdates = [];
        } else {
            $specUpdates = $this->sanitizeSpecUpdates($rawSpecUpdates, $definitionsById);
        }

        $staticUpdates = $this->sanitizeStaticUpdates(
            $rawStaticUpdates,
            (int) $asset->type,
        );

        return [
            'description' => $description !== null ? $this->truncateDescription($description) : null,
            'spec_updates' => $specUpdates,
            'static_updates' => $staticUpdates,
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You assist staff at a marine dealership completing inventory asset records.

You receive JSON: display_name, asset_make (boat brand / manufacturer name if known), description (may be empty), has_variants, optional variant rows (id, display_name), custom "specs" (each with id, label, type number|text|boolean|select, unit, options for selects, and current value), and "static_fields" (length, width, hull_type, hull_material, boat_type) with labels, types, allowed option ids/names, and current values.

Rules:
- Use asset_make and display_name together to infer appropriate boat product terminology when relevant.
- Return plain text for "description" (no HTML). Expand or polish using only facts implied by the input and widely known public marine product knowledge. If the description is already adequate and you would not change it, set "description" to null.
- For custom specs in "spec_updates", only include rows you are reasonably confident about. Match "select" specs using value_text exactly equal to one allowed option "value" from the payload.
- When has_variants is true, specifications are stored per variant, not on the asset. Return "spec_updates" as an empty array. You may still improve "description" using variant names.
- For static_updates: keys must be one of length, width, hull_type, hull_material, boat_type. length and width are integers in millimetres. For hull_type, hull_material, boat_type set value_number to a valid option id from the payload (integers). Omit keys you cannot infer.
- Never fabricate serial numbers, HINs, or customer-specific data.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'description' => [
                    'type' => ['string', 'null'],
                    'description' => 'Plain-text description, or null to leave unchanged',
                ],
                'spec_updates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'spec_id' => ['type' => 'integer'],
                            'value_number' => ['type' => ['number', 'null']],
                            'value_text' => ['type' => ['string', 'null']],
                            'value_boolean' => ['type' => ['boolean', 'null']],
                        ],
                        'required' => ['spec_id', 'value_number', 'value_text', 'value_boolean'],
                        'additionalProperties' => false,
                    ],
                ],
                'static_updates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'value_number' => ['type' => ['number', 'null']],
                            'value_text' => ['type' => ['string', 'null']],
                            'value_boolean' => ['type' => ['boolean', 'null']],
                        ],
                        'required' => ['key', 'value_number', 'value_text', 'value_boolean'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['description', 'spec_updates', 'static_updates'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<int, AssetSpecDefinition>
     */
    private function allowedSpecDefinitions(int $assetType): array
    {
        $defs = AvailableAssetSpecsCache::get($assetType);
        $byId = [];
        foreach ($defs as $def) {
            if ($def instanceof AssetSpecDefinition) {
                $byId[(int) $def->id] = $def;
            }
        }

        return $byId;
    }

    private function resolveAssetMakeDisplay(Asset $asset, array $context): ?string
    {
        $asset->loadMissing('make');
        $v = $context['asset_make'] ?? null;
        if (! is_string($v) || trim($v) === '') {
            $v = $asset->make?->display_name;
        }
        if (! is_string($v)) {
            return null;
        }
        $t = mb_substr(trim($v), 0, 255);

        return $t === '' ? null : $t;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, AssetSpecDefinition>  $definitionsById
     * @return list<array{spec_id: int, value_number: float|int|null, value_text: ?string, value_boolean: ?bool, unit: ?string}>
     */
    private function sanitizeSpecUpdates(array $rows, array $definitionsById): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $specId = isset($row['spec_id']) ? (int) $row['spec_id'] : 0;
            if ($specId <= 0) {
                continue;
            }
            $def = $definitionsById[$specId] ?? null;
            if (! $def instanceof AssetSpecDefinition) {
                continue;
            }

            $type = (string) $def->type;
            $item = [
                'spec_id' => $specId,
                'value_number' => null,
                'value_text' => null,
                'value_boolean' => null,
                'unit' => $def->unit,
            ];

            if ($type === 'number') {
                if (isset($row['value_number']) && is_numeric($row['value_number'])) {
                    $item['value_number'] = round((float) $row['value_number'], 4);
                }
            } elseif ($type === 'boolean') {
                if (array_key_exists('value_boolean', $row) && $row['value_boolean'] !== null) {
                    $item['value_boolean'] = (bool) $row['value_boolean'];
                }
            } elseif ($type === 'text') {
                if (isset($row['value_text']) && is_string($row['value_text'])) {
                    $t = trim($row['value_text']);
                    $item['value_text'] = $t === '' ? null : mb_substr($t, 0, 2000);
                }
            } elseif ($type === 'select') {
                if (isset($row['value_text']) && is_string($row['value_text'])) {
                    $raw = trim($row['value_text']);
                    if ($raw !== '' && $this->selectOptionExists($def, $raw)) {
                        $item['value_text'] = $raw;
                    }
                }
            }

            if (
                $item['value_number'] === null
                && $item['value_text'] === null
                && $item['value_boolean'] === null
            ) {
                continue;
            }

            $out[] = $item;
        }

        return $out;
    }

    private function selectOptionExists(AssetSpecDefinition $def, string $value): bool
    {
        $options = $def->options ?? [];
        if (! is_array($options)) {
            return false;
        }
        foreach ($options as $opt) {
            if (! is_array($opt)) {
                continue;
            }
            if (isset($opt['value']) && (string) $opt['value'] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{key: string, value_number: ?float, value_text: ?string, value_boolean: ?bool}>
     */
    private function sanitizeStaticUpdates(array $rows, int $assetType): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = isset($row['key']) ? (string) $row['key'] : '';
            if (! in_array($key, self::STATIC_SPEC_KEYS, true)) {
                continue;
            }
            if (in_array($key, ['hull_type', 'hull_material', 'boat_type'], true) && $assetType !== 1) {
                continue;
            }

            if ($key === 'length' || $key === 'width') {
                if (! isset($row['value_number']) || ! is_numeric($row['value_number'])) {
                    continue;
                }
                $mm = (int) round((float) $row['value_number']);
                if ($mm < 0 || $mm > 10000000) {
                    continue;
                }
                $out[] = [
                    'key' => $key,
                    'value_number' => (float) $mm,
                    'value_text' => null,
                    'value_boolean' => null,
                ];

                continue;
            }

            if (! isset($row['value_number']) || ! is_numeric($row['value_number'])) {
                continue;
            }
            $id = (int) round((float) $row['value_number']);
            if ($id <= 0) {
                continue;
            }

            $valid = match ($key) {
                'hull_type' => $this->enumIdValid(HullType::class, $id),
                'hull_material' => $this->enumIdValid(HullMaterial::class, $id),
                'boat_type' => $this->enumIdValid(BoatType::class, $id),
                default => false,
            };

            if (! $valid) {
                continue;
            }

            $out[] = [
                'key' => $key,
                'value_number' => (float) $id,
                'value_text' => null,
                'value_boolean' => null,
            ];
        }

        return $out;
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     */
    private function enumIdValid(string $enumClass, int $id): bool
    {
        if (! enum_exists($enumClass)) {
            return false;
        }
        if (! method_exists($enumClass, 'cases')) {
            return false;
        }
        foreach ($enumClass::cases() as $case) {
            if (method_exists($case, 'id') && (int) $case->id() === $id) {
                return true;
            }
        }

        return false;
    }

    private function truncateDescription(string $text): string
    {
        $text = str_replace(["\0"], '', $text);

        return mb_substr($text, 0, 60000);
    }

    /**
     * AI suggestions for a single asset variant (name, description, dimensions, pricing, dynamic specs).
     *
     * @param  array<string, mixed>  $context
     * @return array{name: ?string, description: ?string, length: ?int, width: ?int, default_cost: ?float, default_price: ?float, spec_updates: list<array<string, mixed>>}
     */
    public function suggestVariant(Asset $asset, array $context): array
    {
        $apiKey = config('openai.api_key') ?? env('OPENAI_API_KEY');
        if (! $apiKey) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $definitionsById = $this->allowedSpecDefinitions((int) $asset->type);

        $userPayload = [
            'asset_id' => $asset->id,
            'asset_type' => (int) $asset->type,
            'asset_display_name' => $context['asset_display_name'] ?? $asset->display_name,
            'asset_make' => $this->resolveAssetMakeDisplay($asset, $context),
            'sibling_variant_names' => $context['sibling_variant_names'] ?? [],
            'variant' => [
                'name' => $context['name'] ?? null,
                'description' => $context['description'] ?? null,
                'length' => $context['length'] ?? null,
                'width' => $context['width'] ?? null,
                'default_cost' => $context['default_cost'] ?? null,
                'default_price' => $context['default_price'] ?? null,
            ],
            'specs' => $context['specs'] ?? [],
        ];

        $model = (string) config('boat_meta_ai.generate_model', 'gpt-4o-mini');

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'asset_variant_details_suggestion',
                        'strict' => false,
                        'schema' => $this->variantResponseSchema(),
                    ],
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->variantSystemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($userPayload, JSON_THROW_ON_ERROR),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('AssetDetailsAiService variant OpenAI call failed', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('AI request failed. Please try again.');
        }

        $content = $response->choices[0]->message->content ?? null;
        if ($content === null || $content === '') {
            throw new \RuntimeException('Empty response from AI.');
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid AI response shape.');
        }

        $name = array_key_exists('name', $decoded) && is_string($decoded['name'])
            ? mb_substr(trim($decoded['name']), 0, 255)
            : null;
        if ($name === '') {
            $name = null;
        }

        $description = array_key_exists('description', $decoded) && is_string($decoded['description'])
            ? $this->truncateDescription($decoded['description'])
            : null;
        if ($description !== null && trim($description) === '') {
            $description = null;
        }

        $length = $this->sanitizeVariantMm($decoded['length'] ?? null);
        $width = $this->sanitizeVariantMm($decoded['width'] ?? null);
        $defaultCost = $this->sanitizeVariantMoney($decoded['default_cost'] ?? null);
        $defaultPrice = $this->sanitizeVariantMoney($decoded['default_price'] ?? null);

        $rawSpecUpdates = is_array($decoded['spec_updates'] ?? null) ? $decoded['spec_updates'] : [];
        $specUpdates = $this->sanitizeSpecUpdates($rawSpecUpdates, $definitionsById);

        return [
            'name' => $name,
            'description' => $description,
            'length' => $length,
            'width' => $width,
            'default_cost' => $defaultCost,
            'default_price' => $defaultPrice,
            'spec_updates' => $specUpdates,
        ];
    }

    private function variantSystemPrompt(): string
    {
        return <<<'PROMPT'
You assist staff at a marine dealership completing a single inventory asset VARIANT (one configuration of a parent asset that has variants).

You receive JSON: parent asset_display_name, asset_make (boat brand / manufacturer if known), asset_type, optional sibling_variant_names (other configurations on the same asset), the current variant draft (name, description, length and width in millimetres if set, default_cost, default_price), and custom "specs" (id, label, type number|text|boolean|select, unit, select options, current value).

Rules:
- Return plain text for "description" (no HTML), or null if unchanged.
- Return "name" as a concise configuration label (e.g. engine package, color trim) or null if unchanged.
- "length" and "width" are integers in millimetres, or null if unknown.
- default_cost and default_price are decimal numbers in the account currency, or null.
- "spec_updates": only include spec_id rows you are reasonably confident about; for select specs use value_text exactly equal to an allowed option "value" from the payload.
- Use facts from the draft, parent asset name, asset_make (brand), sibling names, and widely known public marine product knowledge. Never invent HINs, serial numbers, or customer-specific data.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function variantResponseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => ['string', 'null']],
                'description' => ['type' => ['string', 'null']],
                'length' => ['type' => ['number', 'null']],
                'width' => ['type' => ['number', 'null']],
                'default_cost' => ['type' => ['number', 'null']],
                'default_price' => ['type' => ['number', 'null']],
                'spec_updates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'spec_id' => ['type' => 'integer'],
                            'value_number' => ['type' => ['number', 'null']],
                            'value_text' => ['type' => ['string', 'null']],
                            'value_boolean' => ['type' => ['boolean', 'null']],
                        ],
                        'required' => ['spec_id', 'value_number', 'value_text', 'value_boolean'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['name', 'description', 'length', 'width', 'default_cost', 'default_price', 'spec_updates'],
            'additionalProperties' => false,
        ];
    }

    private function sanitizeVariantMm(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $mm = (int) round((float) $value);
        if ($mm < 0 || $mm > 10000000) {
            return null;
        }

        return $mm;
    }

    private function sanitizeVariantMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = round((float) $value, 2);
        if ($n < 0 || $n > 99999999.99) {
            return null;
        }

        return $n;
    }
}
