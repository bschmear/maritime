<?php

declare(strict_types=1);

namespace App\Services\Asset;

use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use App\Support\Asset\BoatSpecFillerContextBuilder;
use App\Support\OpenAi\OpenAiModelResolver;
use App\Support\OpenAi\OpenAiRequestType;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

final class BoatSpecFillerService
{
    /**
     * @param  array<string, mixed>  $context  Output from BoatSpecFillerContextBuilder
     * @return array{
     *   tenant_id: string,
     *   model_name: string,
     *   specs: array<string, mixed>,
     *   confidence: float,
     *   data_source_type: string,
     *   cached: bool,
     *   preview_rows: list<array{key: string, label: string, value: string|null, kind: string}>,
     *   spec_updates: list<array<string, mixed>>,
     *   static_updates: list<array<string, mixed>>
     * }
     */
    public function suggest(array $context, bool $refresh = false): array
    {
        $tenantId = (string) $context['tenant_id'];
        $modelName = (string) $context['model_name'];
        $schemaHash = (string) $context['schema_hash'];

        if (! $refresh) {
            $cached = BoatSpecFillerCache::get($tenantId, $modelName, $schemaHash);
            if (is_array($cached)) {
                return $this->finalize($cached, $context, true);
            }
        } else {
            BoatSpecFillerCache::forget($tenantId, $modelName, $schemaHash);
        }

        $apiKey = config('openai.api_key') ?? env('OPENAI_API_KEY');
        if (! $apiKey) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $userPayload = [
            'tenant_id' => $tenantId,
            'model_name' => $modelName,
            'spec_fields' => $context['spec_fields'],
        ];

        $model = OpenAiModelResolver::resolve(OpenAiRequestType::BoatSpecs);

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'boat_spec_filler',
                        'strict' => true,
                        'schema' => $this->responseSchema($context['spec_fields']),
                    ],
                ],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => json_encode($userPayload, JSON_THROW_ON_ERROR)],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('BoatSpecFillerService OpenAI call failed', [
                'model_name' => $modelName,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('AI request failed. Please try again.');
        }

        $content = $response->choices[0]->message->content ?? null;
        if ($content === null || $content === '') {
            throw new \RuntimeException('Empty response from AI.');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        BoatSpecFillerCache::put($tenantId, $modelName, $schemaHash, $decoded);

        return $this->finalize($decoded, $context, false);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are agent "boat_spec_filler" v1.0.

Rules (strict):
- no_hallucination: true
- no_guessing: true
- no_external_inference: true
- only_return_known_data: true
- unknown_values: null
- strict_schema_compliance: true

For each requested spec field in spec_fields:
- Find verified manufacturer-level data only for the given model_name.
- If not explicitly known from authoritative manufacturer/product documentation, return null.
- Do NOT estimate, approximate, or infer from similar models.
- If unit mismatch occurs, return null instead of converting.
- If multiple conflicting values exist, return null.

Set confidence 0-1 based on data certainty.
Set data_source_type to manufacturer_verified, partial_knowledge, or unknown.

Return JSON matching the output schema exactly.
PROMPT;
    }

    /**
     * @param  list<array{name: string, type: string, unit: string|null, required: bool}>  $specFields
     * @return array<string, mixed>
     */
    private function responseSchema(array $specFields): array
    {
        $specProperties = [];
        foreach ($specFields as $field) {
            $name = (string) $field['name'];
            $specProperties[$name] = [
                'type' => ['string', 'number', 'integer', 'boolean', 'null'],
            ];
        }

        return [
            'type' => 'object',
            'properties' => [
                'tenant_id' => ['type' => 'string'],
                'model_name' => ['type' => 'string'],
                'specs' => [
                    'type' => 'object',
                    'properties' => $specProperties,
                    'required' => array_keys($specProperties),
                    'additionalProperties' => false,
                ],
                'confidence' => ['type' => 'number'],
                'data_source_type' => [
                    'type' => 'string',
                    'enum' => ['manufacturer_verified', 'partial_knowledge', 'unknown'],
                ],
            ],
            'required' => ['tenant_id', 'model_name', 'specs', 'confidence', 'data_source_type'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $aiResult
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function finalize(array $aiResult, array $context, bool $cached): array
    {
        /** @var array<string, mixed> $rawSpecs */
        $rawSpecs = is_array($aiResult['specs'] ?? null) ? $aiResult['specs'] : [];

        /** @var array<string, AssetSpecDefinition> $definitionsByKey */
        $definitionsByKey = $context['definitions_by_key'] ?? [];
        /** @var list<string> $staticKeys */
        $staticKeys = $context['static_keys'] ?? [];

        $specUpdates = $this->mapDynamicUpdates($rawSpecs, $definitionsByKey);
        $staticUpdates = $this->mapStaticUpdates($rawSpecs, $staticKeys);

        $previewRows = $this->buildPreviewRows(
            $rawSpecs,
            $definitionsByKey,
            $staticKeys,
            $context['spec_fields'] ?? [],
        );

        return [
            'tenant_id' => (string) ($aiResult['tenant_id'] ?? $context['tenant_id']),
            'model_name' => (string) ($aiResult['model_name'] ?? $context['model_name']),
            'specs' => $rawSpecs,
            'confidence' => (float) ($aiResult['confidence'] ?? 0),
            'data_source_type' => (string) ($aiResult['data_source_type'] ?? 'unknown'),
            'cached' => $cached,
            'preview_rows' => $previewRows,
            'spec_updates' => $specUpdates,
            'static_updates' => $staticUpdates,
        ];
    }

    /**
     * @param  array<string, mixed>  $rawSpecs
     * @param  array<string, AssetSpecDefinition>  $definitionsByKey
     * @return list<array<string, mixed>>
     */
    private function mapDynamicUpdates(array $rawSpecs, array $definitionsByKey): array
    {
        $out = [];
        foreach ($definitionsByKey as $key => $def) {
            if (! array_key_exists($key, $rawSpecs)) {
                continue;
            }
            $value = $rawSpecs[$key];
            if ($value === null) {
                continue;
            }

            $item = [
                'spec_id' => (int) $def->id,
                'value_number' => null,
                'value_text' => null,
                'value_boolean' => null,
                'unit' => $def->unit,
            ];

            $type = (string) $def->type;
            if ($type === 'number' && is_numeric($value)) {
                $item['value_number'] = round((float) $value, 4);
            } elseif ($type === 'boolean') {
                $item['value_boolean'] = (bool) $value;
            } elseif ($type === 'text' && is_string($value)) {
                $t = trim($value);
                $item['value_text'] = $t === '' ? null : mb_substr($t, 0, 2000);
            } elseif ($type === 'select' && is_string($value)) {
                $raw = trim($value);
                if ($raw !== '' && $this->selectOptionExists($def, $raw)) {
                    $item['value_text'] = $raw;
                }
            }

            if ($item['value_number'] === null && $item['value_text'] === null && $item['value_boolean'] === null) {
                continue;
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $rawSpecs
     * @param  list<string>  $staticKeys
     * @return list<array<string, mixed>>
     */
    private function mapStaticUpdates(array $rawSpecs, array $staticKeys): array
    {
        $out = [];
        foreach ($staticKeys as $key) {
            if (! array_key_exists($key, $rawSpecs)) {
                continue;
            }
            $value = $rawSpecs[$key];
            if ($value === null) {
                continue;
            }

            if ($key === 'length' || $key === 'width') {
                if (! is_numeric($value)) {
                    continue;
                }
                $mm = (int) round((float) $value);
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

            if (! is_numeric($value) && ! is_string($value)) {
                continue;
            }

            $id = is_numeric($value) ? (int) round((float) $value) : null;
            if ($id === null || $id <= 0) {
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
     * @param  array<string, mixed>  $rawSpecs
     * @param  array<string, AssetSpecDefinition>  $definitionsByKey
     * @param  list<string>  $staticKeys
     * @param  list<array{name: string, type: string, unit: string|null, required: bool}>  $specFields
     * @return list<array{key: string, label: string, value: string|null, kind: string}>
     */
    private function buildPreviewRows(
        array $rawSpecs,
        array $definitionsByKey,
        array $staticKeys,
        array $specFields,
    ): array {
        $staticLabels = [
            'length' => 'Length',
            'width' => 'Width',
            'hull_type' => 'Hull type',
            'hull_material' => 'Hull material',
            'boat_type' => 'Boat type',
        ];

        $rows = [];
        foreach ($specFields as $field) {
            $key = (string) $field['name'];
            $value = $rawSpecs[$key] ?? null;
            $kind = in_array($key, $staticKeys, true) ? 'static' : 'dynamic';
            $label = $staticLabels[$key] ?? ($definitionsByKey[$key]->label ?? $key);
            $unit = $field['unit'] ?? null;

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'value' => $this->formatPreviewValue($value, (string) $field['type'], $unit, $key, $definitionsByKey[$key] ?? null),
                'kind' => $kind,
            ];
        }

        return $rows;
    }

    private function formatPreviewValue(
        mixed $value,
        string $type,
        ?string $unit,
        string $key,
        ?AssetSpecDefinition $def,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($key === 'hull_type') {
            return $this->enumLabel(HullType::class, (int) $value) ?? (string) $value;
        }
        if ($key === 'hull_material') {
            return $this->enumLabel(HullMaterial::class, (int) $value) ?? (string) $value;
        }
        if ($key === 'boat_type') {
            return $this->enumLabel(BoatType::class, (int) $value) ?? (string) $value;
        }

        if ($type === 'boolean') {
            return $value ? 'Yes' : 'No';
        }

        if ($type === 'select' && $def instanceof AssetSpecDefinition && is_string($value)) {
            foreach ($def->options ?? [] as $opt) {
                if (is_array($opt) && isset($opt['value']) && (string) $opt['value'] === $value) {
                    return isset($opt['label']) ? (string) $opt['label'] : $value;
                }
            }
        }

        $str = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;

        return ($unit !== null && $unit !== '') ? trim($str.' '.$unit) : $str;
    }

    private function selectOptionExists(AssetSpecDefinition $def, string $value): bool
    {
        foreach ($def->options ?? [] as $opt) {
            if (is_array($opt) && isset($opt['value']) && (string) $opt['value'] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     */
    private function enumIdValid(string $enumClass, int $id): bool
    {
        if (! enum_exists($enumClass) || ! method_exists($enumClass, 'cases')) {
            return false;
        }
        foreach ($enumClass::cases() as $case) {
            if (method_exists($case, 'id') && (int) $case->id() === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     */
    private function enumLabel(string $enumClass, int $id): ?string
    {
        if (! enum_exists($enumClass) || ! method_exists($enumClass, 'cases')) {
            return null;
        }
        foreach ($enumClass::cases() as $case) {
            if (method_exists($case, 'id') && (int) $case->id() === $id) {
                return method_exists($case, 'label') ? (string) $case->label() : (string) $case->name;
            }
        }

        return null;
    }
}
