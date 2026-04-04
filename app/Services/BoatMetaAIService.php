<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use App\Enums\Inventory\BoatType;

class BoatMetaAIService
{
    public function generate(string $makeSlug, string $modelSlug, string $makeLabel, string $modelLabel): array
    {
        // 1. Load existing meta
        $metaPath = $this->getMetaPath($makeSlug);
        $meta = $this->loadMeta($metaPath);

        // 2. Prevent overwrite
        if (isset($meta[$modelSlug])) {
            return $meta[$modelSlug];
        }

        // 3. Call AI
        $aiData = $this->callAI($makeSlug, $modelSlug, $makeLabel, $modelLabel);

        // 4. Validate structure
        $this->validate($aiData);

        // 5. Normalize data
        $normalized = $this->normalize($aiData);

        // 6. Save
        $meta[$modelSlug] = $normalized;
        $this->saveMeta($metaPath, $meta);

        return $normalized;
    }

    protected function callAI($makeSlug, $modelSlug, $makeLabel, $modelLabel): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-5',
            'temperature' => 0.2,

            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'boat_model_meta',
                    'schema' => $this->schema()
                ]
            ],

            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'task' => 'generate_boat_model_metadata',
                        'make' => $makeLabel,
                        'model' => $modelLabel,
                        'make_slug' => $makeSlug,
                        'model_slug' => $modelSlug
                    ])
                ]
            ]
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }

    protected function validate(array $data): void
    {
        // Hard guard: cannot have both
        if (isset($data['variants']) && isset($data['specifications'])) {
            throw new \Exception('Invalid AI response: both variants and specifications present.');
        }

        Validator::make($data, [
            'series' => 'required|string',
            'type_display' => 'required|string',
            'boat_type_key' => ['required', Rule::in(array_column(BoatType::options(), 'value'))],
            'description' => 'required|string',
            'features' => 'required|array',

            'specifications' => 'nullable|array',
            'variants' => 'nullable|array'
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
        if (!$value) return null;

        // naive cleanup (can improve later)
        $value = str_replace(['ft', 'feet'], "'", strtolower($value));
        $value = str_replace(['in', 'inches'], '"', $value);

        return trim($value);
    }

    protected function normalizeWeight($value): ?string
    {
        if (!$value) return null;

        return preg_replace('/[^0-9]/', '', $value) . ' lbs';
    }

    protected function normalizeHP($value): ?string
    {
        if (!$value) return null;

        return preg_replace('/[^0-9]/', '', $value) . 'hp';
    }

    protected function normalizeGallons($value): ?string
    {
        if (!$value) return null;

        return preg_replace('/[^0-9]/', '', $value) . ' gal';
    }

    protected function normalizeInt($value): ?int
    {
        if ($value === null) return null;

        return (int) $value;
    }

    protected function getMetaPath(string $makeSlug): string
    {
        return app_path("AssetInformation/{$makeSlug}/meta.json");
    }

    protected function loadMeta(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }

    protected function saveMeta(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    }

    protected function systemPrompt(): string
    {
        return <<<SYS
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

VARIANTS:
- Include only if real
- Otherwise use specifications
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
                    'enum' => array_column(BoatType::options(), 'value')
                ],
                'description' => ['type' => 'string'],
                'features' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'specifications' => [
                    'type' => ['object', 'null'],
                    'properties' => $this->specSchema()
                ],
                'variants' => [
                    'type' => ['array', 'null'],
                    'items' => [
                        'type' => 'object',
                        'required' => ['id', 'name', 'specifications'],
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'specifications' => [
                                'type' => 'object',
                                'properties' => $this->specSchema()
                            ]
                        ]
                    ]
                ]
            ],
            'oneOf' => [
                ['required' => ['specifications']],
                ['required' => ['variants']]
            ]
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