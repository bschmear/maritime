<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Support;

use App\Domain\Asset\Support\SyncAssetSpecValues;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAsset;
use App\Domain\InventoryCatalog\Models\InventoryCatalogAssetVariant;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps inventory catalog measurements into tenant {@see AssetSpecValue} rows for seeded definitions.
 */
final class CatalogImportSpecSync
{
    public static function kilogramsToPounds(int $kg): float
    {
        return round($kg * 2.2046226218, 1);
    }

    /**
     * Inventory specification keys mapped to tenant {@see AssetSpecDefinition} keys.
     *
     * @var list<string>
     */
    private const SPEC_DEFINITION_KEYS = [
        'boat_weight',
        'max_people',
        'max_hp',
        'engine_shaft',
    ];

    public static function syncForInventoryRow(
        Model $specable,
        int $assetType,
        InventoryCatalogAsset|InventoryCatalogAssetVariant $src,
    ): void {
        $definitions = AssetSpecDefinition::query()
            ->whereIn('key', self::SPEC_DEFINITION_KEYS)
            ->get()
            ->keyBy('key');

        if ($definitions->isEmpty()) {
            return;
        }

        $payload = [];

        $weightKg = InventoryCatalogSpecificationReader::effectiveUInt($src, 'weight_kg', 'weight_kg');
        if ($weightKg !== null && $definitions->has('boat_weight')) {
            $def = $definitions->get('boat_weight');
            $payload[] = [
                'spec_id' => $def->id,
                'value_number' => self::kilogramsToPounds($weightKg),
                'unit' => $def->unit ?? 'lb',
            ];
        }

        $people = InventoryCatalogSpecificationReader::effectiveUInt($src, 'capacity_persons', 'capacity_persons');
        if ($people !== null && $definitions->has('max_people')) {
            $def = $definitions->get('max_people');
            $payload[] = [
                'spec_id' => $def->id,
                'value_number' => $people,
                'unit' => $def->unit,
            ];
        }

        $maxHp = InventoryCatalogSpecificationReader::effectiveUInt($src, 'max_hp', 'max_hp');
        if ($maxHp !== null && $definitions->has('max_hp')) {
            $def = $definitions->get('max_hp');
            $payload[] = [
                'spec_id' => $def->id,
                'value_number' => $maxHp,
                'unit' => $def->unit ?? 'hp',
            ];
        }

        $shaft = $src->getAttribute('engine_shaft');
        if (($shaft === null || $shaft === '') && $definitions->has('engine_shaft')) {
            $layer = InventoryCatalogSpecificationReader::attributeLayer($src);
            $shaft = $layer['engine_shaft'] ?? null;
        }
        if (is_string($shaft) && trim($shaft) !== '' && $definitions->has('engine_shaft')) {
            $def = $definitions->get('engine_shaft');
            $payload[] = [
                'spec_id' => $def->id,
                'value_text' => strtoupper(trim($shaft)),
            ];
        }

        if ($payload === []) {
            return;
        }

        SyncAssetSpecValues::forSpecable($specable, $assetType, $payload);
    }
}
