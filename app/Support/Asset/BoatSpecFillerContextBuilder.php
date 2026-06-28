<?php

declare(strict_types=1);

namespace App\Support\Asset;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use Illuminate\Support\Collection;

final class BoatSpecFillerContextBuilder
{
    /** @var list<string> */
    private const ASSET_STATIC_KEYS = ['length', 'width', 'hull_type', 'hull_material', 'boat_type'];

    /** @var list<string> */
    private const VARIANT_STATIC_KEYS = ['length', 'width'];

    /**
     * @return array{
     *   tenant_id: string,
     *   model_name: string,
     *   spec_fields: list<array{name: string, type: string, unit: string|null, required: bool}>,
     *   schema_hash: string,
     *   definitions_by_key: array<string, AssetSpecDefinition>,
     *   static_keys: list<string>
     * }
     */
    public static function forAsset(Asset $asset): array
    {
        $asset->loadMissing(['make', 'specValues.definition']);

        $assetType = (int) $asset->type;
        $definitions = AvailableAssetSpecsCache::get($assetType);
        $staticKeys = $assetType === 1 ? self::ASSET_STATIC_KEYS : ['length', 'width'];

        return self::build(
            self::resolveModelName($asset),
            $definitions,
            $staticKeys,
            $assetType,
            $asset->specValues,
            $asset,
        );
    }

    /**
     * @return array{
     *   tenant_id: string,
     *   model_name: string,
     *   spec_fields: list<array{name: string, type: string, unit: string|null, required: bool}>,
     *   schema_hash: string,
     *   definitions_by_key: array<string, AssetSpecDefinition>,
     *   static_keys: list<string>
     * }
     */
    public static function forVariant(AssetVariant $variant): array
    {
        $variant->loadMissing(['asset.make', 'specValues.definition']);
        $asset = $variant->asset;
        $assetType = $asset ? (int) $asset->type : 0;
        $definitions = AvailableAssetSpecsCache::get($assetType);

        return self::build(
            self::resolveVariantModelName($variant, $asset),
            $definitions,
            self::VARIANT_STATIC_KEYS,
            $assetType,
            $variant->specValues,
            $variant,
            $asset,
        );
    }

    /**
     * @param  Collection<int, AssetSpecDefinition>  $definitions
     * @param  Collection<int, AssetSpecValue>  $specValues
     * @return array{
     *   tenant_id: string,
     *   model_name: string,
     *   spec_fields: list<array{name: string, type: string, unit: string|null, required: bool}>,
     *   schema_hash: string,
     *   definitions_by_key: array<string, AssetSpecDefinition>,
     *   static_keys: list<string>
     * }
     */
    private static function build(
        string $modelName,
        Collection $definitions,
        array $staticKeys,
        int $assetType,
        Collection $specValues,
        Asset|AssetVariant $record,
        ?Asset $parentAsset = null,
    ): array {
        $specFields = [];
        $definitionsByKey = [];

        foreach ($staticKeys as $key) {
            if ($key === 'hull_type' || $key === 'hull_material' || $key === 'boat_type') {
                if ($assetType !== 1) {
                    continue;
                }
            }

            $specFields[] = [
                'name' => $key,
                'type' => in_array($key, ['length', 'width'], true) ? 'number' : 'string',
                'unit' => in_array($key, ['length', 'width'], true) ? 'mm' : null,
                'required' => false,
                ...self::staticFieldAiHints($key),
            ];
        }

        foreach ($definitions as $def) {
            if (! $def instanceof AssetSpecDefinition) {
                continue;
            }
            $name = self::definitionKey($def);
            $definitionsByKey[$name] = $def;
            $specFields[] = [
                'name' => $name,
                'type' => (string) $def->type,
                'unit' => $def->unit,
                'required' => (bool) $def->is_required,
                ...self::dynamicFieldAiHints($def),
            ];
        }

        $schemaHash = hash('sha256', json_encode($specFields, JSON_THROW_ON_ERROR));

        return [
            'tenant_id' => (string) (tenant()?->id ?? 'central'),
            'make_label' => $parentAsset?->make?->display_name ?? ($record instanceof Asset ? $record->make?->display_name : null),
            'model_label' => $parentAsset !== null
                ? trim((string) ($record instanceof AssetVariant ? ($record->model ?? $record->name ?? '') : ''))
                : trim((string) ($record instanceof Asset ? ($record->model ?? '') : '')),
            'model_name' => $modelName,
            'spec_fields' => $specFields,
            'schema_hash' => $schemaHash,
            'definitions_by_key' => $definitionsByKey,
            'static_keys' => $staticKeys,
            'current_values' => self::currentValues($record, $parentAsset, $definitionsByKey, $staticKeys, $specValues),
        ];
    }

    private static function resolveModelName(Asset $asset): string
    {
        $parts = array_filter([
            $asset->make?->display_name,
            $asset->model,
            $asset->year,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        if ($parts !== []) {
            return mb_substr(trim(implode(' ', $parts)), 0, 255);
        }

        return mb_substr(trim((string) $asset->display_name), 0, 255);
    }

    private static function resolveVariantModelName(AssetVariant $variant, ?Asset $asset): string
    {
        $base = $asset ? self::resolveModelName($asset) : '';
        $variantName = trim((string) ($variant->name ?? $variant->display_name ?? ''));

        if ($base !== '' && $variantName !== '') {
            return mb_substr($base.' — '.$variantName, 0, 255);
        }

        return mb_substr($variantName !== '' ? $variantName : $base, 0, 255);
    }

    private static function definitionKey(AssetSpecDefinition $def): string
    {
        $key = trim((string) ($def->key ?? ''));
        if ($key !== '') {
            return $key;
        }

        return 'spec_'.$def->id;
    }

    /**
     * @param  array<string, AssetSpecDefinition>  $definitionsByKey
     * @param  list<string>  $staticKeys
     * @param  Collection<int, AssetSpecValue>  $specValues
     * @return array<string, mixed>
     */
    private static function currentValues(
        Asset|AssetVariant $record,
        ?Asset $parentAsset,
        array $definitionsByKey,
        array $staticKeys,
        Collection $specValues,
    ): array {
        $values = [];

        foreach ($staticKeys as $key) {
            $values[$key] = $record->{$key} ?? ($parentAsset?->{$key} ?? null);
        }

        $valuesByDefId = [];
        foreach ($specValues as $sv) {
            if ($sv instanceof AssetSpecValue) {
                $valuesByDefId[(int) $sv->asset_spec_definition_id] = $sv;
            }
        }

        foreach ($definitionsByKey as $name => $def) {
            $sv = $valuesByDefId[(int) $def->id] ?? null;
            $values[$name] = match ((string) $def->type) {
                'number' => $sv?->value_number,
                'boolean' => $sv?->value_boolean,
                default => $sv?->value_text,
            };
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private static function staticFieldAiHints(string $key): array
    {
        return match ($key) {
            'hull_type' => ['allowed_values' => self::enumOptions(HullType::class)],
            'hull_material' => ['allowed_values' => self::enumOptions(HullMaterial::class)],
            'boat_type' => ['allowed_values' => self::enumOptions(BoatType::class)],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function dynamicFieldAiHints(AssetSpecDefinition $def): array
    {
        if ((string) $def->type !== 'select' || ! is_array($def->options)) {
            return [];
        }

        $options = [];
        foreach ($def->options as $opt) {
            if (! is_array($opt) || ! isset($opt['value'])) {
                continue;
            }
            $options[] = [
                'value' => (string) $opt['value'],
                'label' => isset($opt['label']) ? (string) $opt['label'] : (string) $opt['value'],
            ];
        }

        return $options === [] ? [] : ['options' => $options];
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     * @return list<array{id: int, label: string}>
     */
    private static function enumOptions(string $enumClass): array
    {
        if (! enum_exists($enumClass) || ! method_exists($enumClass, 'options')) {
            return [];
        }

        return array_map(
            fn (array $row) => ['id' => (int) $row['id'], 'label' => (string) $row['name']],
            $enumClass::options(),
        );
    }
}
