<?php

namespace App\Services;

use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAsset;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAssetVariant;
use App\Enums\Inventory\BoatType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenAI\Laravel\Facades\OpenAI;

class BoatMetaAIService
{
    public function generate(string $makeSlug, string $modelSlug, string $makeLabel, string $modelLabel): array
    {
        $invMake = InventoryBoatMake::query()->firstOrCreate(
            ['slug' => $makeSlug],
            ['display_name' => $makeLabel, 'active' => true]
        );

        $catalogKey = $makeSlug.'--'.$modelSlug;

        $existing = InventoryCatalogAsset::query()
            ->where('make_id', $invMake->id)
            ->where('slug', $catalogKey)
            ->first();

        if ($existing !== null) {
            $meta = data_get($existing->attributes, 'boat_meta');
            if (is_array($meta)) {
                return $meta;
            }
        }

        $aiData = $this->callAI($makeSlug, $modelSlug, $makeLabel, $modelLabel);
        $this->validate($aiData);
        $normalized = $this->normalize($aiData);
        $this->persistToInventory($invMake, $catalogKey, $modelLabel, $normalized);

        return $normalized;
    }

    protected function persistToInventory(InventoryBoatMake $invMake, string $catalogKey, string $modelLabel, array $normalized): void
    {
        DB::connection('inventory')->transaction(function () use ($invMake, $catalogKey, $modelLabel, $normalized): void {
            $specs = $normalized['specifications'] ?? [];
            $attrs = [
                'boat_meta' => $normalized,
                'boat_type_key' => $normalized['boat_type_key'] ?? null,
                'features' => $normalized['features'] ?? [],
                'series' => $normalized['series'] ?? null,
                'type_display' => $normalized['type_display'] ?? null,
            ];

            $hasVariants = isset($normalized['variants']) && is_array($normalized['variants']) && count($normalized['variants']) > 0;

            $asset = InventoryCatalogAsset::query()->updateOrCreate(
                [
                    'make_id' => $invMake->id,
                    'slug' => $catalogKey,
                ],
                [
                    'type' => 1,
                    'display_name' => trim(($normalized['series'] ?? '').' '.$modelLabel) ?: $modelLabel,
                    'inactive' => false,
                    'model' => $modelLabel,
                    'description' => $normalized['description'] ?? null,
                    'attributes' => $attrs,
                    'has_variants' => $hasVariants,
                    'length' => $specs['length'] ?? null,
                    'beam' => $specs['width'] ?? null,
                    'persons' => $specs['capacity_persons'] ?? null,
                    'maximum_power' => $this->hpStringToInt($specs['max_hp'] ?? null),
                    'fuel_tank' => $specs['fuel_capacity'] ?? null,
                ]
            );

            InventoryCatalogAssetVariant::query()->where('asset_id', $asset->id)->delete();

            if ($hasVariants) {
                foreach ($normalized['variants'] as $v) {
                    InventoryCatalogAssetVariant::query()->create([
                        'asset_id' => $asset->id,
                        'key' => $v['id'],
                        'name' => $v['name'],
                        'display_name' => $v['name'],
                        'inactive' => false,
                        'description' => json_encode(['specifications' => $v['specifications'] ?? []]),
                    ]);
                }
            }
        });
    }

    protected function hpStringToInt(?string $hp): ?int
    {
        if ($hp === null || $hp === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $hp);

        return $digits !== '' && $digits !== null ? (int) $digits : null;
    }

    protected function callAI(string $makeSlug, string $modelSlug, string $makeLabel, string $modelLabel): array
    {
        $response = OpenAI::chat()->create([
            'model' => config('boat_meta_ai.generate_model', 'gpt-5'),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'boat_model_meta',
                    'schema' => $this->schema(),
                ],
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'task' => 'generate_boat_model_metadata',
                        'make' => $makeLabel,
                        'model' => $modelLabel,
                        'make_slug' => $makeSlug,
                        'model_slug' => $modelSlug,
                    ]),
                ],
            ],
        ]);

        return json_decode($response->choices[0]->message->content, true, 512, JSON_THROW_ON_ERROR);
    }

    protected function validate(array $data): void
    {
        $specs = $data['specifications'] ?? null;
        $vars = $data['variants'] ?? null;

        $hasSpecs = $specs !== null;
        $hasVariants = is_array($vars) && count($vars) > 0;

        if ($hasSpecs && $hasVariants) {
            throw new \Exception('Invalid AI response: include either specifications or variants, not both.');
        }

        if (! $hasSpecs && ! $hasVariants) {
            throw new \Exception('Invalid AI response: must include non-null specifications or a non-empty variants array.');
        }

        Validator::make($data, [
            'series' => 'required|string',
            'type_display' => 'required|string',
            'boat_type_key' => ['required', Rule::in(array_column(BoatType::options(), 'value'))],
            'description' => 'required|string',
            'features' => 'required|array',

            'specifications' => 'nullable',
            'variants' => 'nullable|array',
        ])->validate();
    }

    protected function normalize(array $data): array
    {
        if (isset($data['specifications'])) {
            $data['specifications'] = $this->normalizeSpecs($data['specifications']);
        }

        if (isset($data['variants'])) {
            $data['variants'] = array_map(function ($variant) {
                $variant['id'] = Str::slug($variant['id']);
                $variant['specifications'] = $this->normalizeSpecs($variant['specifications']);

                return $variant;
            }, $data['variants']);
        }

        return $data;
    }

    protected function normalizeSpecs(array $specs): array
    {
        return [
            'length' => $this->normalizeFeetInches($specs['length'] ?? null),
            'width' => $this->normalizeFeetInches($specs['width'] ?? null),
            'height' => $this->normalizeFeetInches($specs['height'] ?? null),
            'weight' => $this->normalizeWeight($specs['weight'] ?? null),
            'capacity_persons' => $this->normalizeInt($specs['capacity_persons'] ?? null),
            'max_hp' => $this->normalizeHP($specs['max_hp'] ?? null),
            'fuel_capacity' => $this->normalizeGallons($specs['fuel_capacity'] ?? null),
        ];
    }

    protected function normalizeFeetInches($value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = str_replace(['ft', 'feet'], "'", strtolower((string) $value));
        $value = str_replace(['in', 'inches'], '"', $value);

        return trim($value);
    }

    protected function normalizeWeight($value): ?string
    {
        if (! $value) {
            return null;
        }

        return preg_replace('/\D+/', '', (string) $value).' lbs';
    }

    protected function normalizeHP($value): ?string
    {
        if (! $value) {
            return null;
        }

        return preg_replace('/\D+/', '', (string) $value).'hp';
    }

    protected function normalizeGallons($value): ?string
    {
        if (! $value) {
            return null;
        }

        return preg_replace('/\D+/', '', (string) $value).' gal';
    }

    protected function normalizeInt($value): ?int
    {
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    protected function systemPrompt(): string
    {
        return <<<'SYS'
You are a marine product data normalization engine.

Return ONLY valid JSON that matches the schema.

RULES:
- No extra fields
- Use null when unknown
- Do not guess specs
- Use:
  - feet/inches (21' 8")
  - lbs
  - hp
  - gallons

VARIANTS VS SPECS (exactly one):
- If the line has multiple size/trim variants: set "variants" to a non-empty array and set "specifications" to null.
- If it is a single configuration: set "specifications" to an object (use null for unknown fields) and set "variants" to null.
- Never send both non-null specifications and a non-empty variants array.
SYS;
    }

    protected function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['series', 'type_display', 'boat_type_key', 'description', 'features'],
            'additionalProperties' => false,
            'properties' => [
                'series' => ['type' => 'string'],
                'type_display' => ['type' => 'string'],
                'boat_type_key' => [
                    'type' => 'string',
                    'enum' => array_column(BoatType::options(), 'value'),
                ],
                'description' => ['type' => 'string'],
                'features' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'specifications' => [
                    'type' => ['object', 'null'],
                    'additionalProperties' => false,
                    'properties' => $this->specSchema(),
                ],
                'variants' => [
                    'type' => ['array', 'null'],
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['id', 'name', 'specifications'],
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'specifications' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => $this->specSchema(),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function specSchema(): array
    {
        return [
            'length' => ['type' => ['string', 'null']],
            'width' => ['type' => ['string', 'null']],
            'height' => ['type' => ['string', 'null']],
            'weight' => ['type' => ['string', 'null']],
            'capacity_persons' => ['type' => ['integer', 'null']],
            'max_hp' => ['type' => ['string', 'null']],
            'fuel_capacity' => ['type' => ['string', 'null']],
        ];
    }
}
