<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;

final class AssetUnitGoogleSheetColumnRegistry
{
    public const HEADER_UNIT_ID = 'Unit ID';

    public const HEADER_SERIAL = 'Serial Number';

    public const HEADER_HIN = 'HIN';

    public const HEADER_SKU = 'SKU';

    public const HEADER_ASSET = 'Asset';

    public const HEADER_MAKE = 'Make';

    public const HEADER_VARIANT = 'Variant';

    public const HEADER_UNIT_YEAR = 'Unit Year';

    public const HEADER_STATUS = 'Status';

    public const HEADER_CONDITION = 'Condition';

    public const HEADER_COST = 'Cost';

    public const HEADER_ASKING_PRICE = 'Asking Price';

    public const HEADER_LOCATION = 'Location';

    public const HEADER_ASSET_MODEL = 'Asset Model';

    public const HEADER_ASSET_YEAR = 'Asset Year';

    public const HEADER_LENGTH = 'Length';

    public const HEADER_WIDTH = 'Width';

    public const HEADER_HULL_TYPE = 'Hull Type';

    public const HEADER_HULL_MATERIAL = 'Hull Material';

    public const HEADER_BOAT_TYPE = 'Boat Type';

    public const HEADER_MAX_HP = 'Max HP';

    public const SPEC_PREFIX = 'Spec: ';

    /**
     * @return list<string>
     */
    public function baseHeaders(): array
    {
        return [
            self::HEADER_UNIT_ID,
            self::HEADER_SERIAL,
            self::HEADER_HIN,
            self::HEADER_SKU,
            self::HEADER_ASSET,
            self::HEADER_MAKE,
            self::HEADER_VARIANT,
            self::HEADER_UNIT_YEAR,
            self::HEADER_STATUS,
            self::HEADER_CONDITION,
            self::HEADER_COST,
            self::HEADER_ASKING_PRICE,
            self::HEADER_LOCATION,
            self::HEADER_ASSET_MODEL,
            self::HEADER_ASSET_YEAR,
            self::HEADER_LENGTH,
            self::HEADER_WIDTH,
            self::HEADER_HULL_TYPE,
            self::HEADER_HULL_MATERIAL,
            self::HEADER_BOAT_TYPE,
            self::HEADER_MAX_HP,
        ];
    }

    /**
     * @return list<AssetSpecDefinition>
     */
    public function specDefinitions(): array
    {
        return AssetSpecDefinition::query()
            ->where('is_visible', true)
            ->orderBy('position')
            ->orderBy('label')
            ->get()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function allHeaders(): array
    {
        $specHeaders = array_map(
            fn (AssetSpecDefinition $def) => self::SPEC_PREFIX.$def->label,
            $this->specDefinitions(),
        );

        return array_merge($this->baseHeaders(), $specHeaders);
    }

    /**
     * @return array<string, int>
     */
    public function headerIndexMap(): array
    {
        $map = [];
        foreach ($this->allHeaders() as $index => $header) {
            $map[$header] = $index;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public function statusLabels(): array
    {
        return array_map(fn (array $o) => (string) $o['name'], UnitStatus::options());
    }

    /**
     * @return list<string>
     */
    public function conditionLabels(): array
    {
        return array_map(fn (array $o) => (string) $o['name'], UnitCondition::options());
    }

    public function enumLabel(?int $id, string $enumClass): string
    {
        if ($id === null || ! class_exists($enumClass) || ! method_exists($enumClass, 'options')) {
            return '';
        }

        foreach ($enumClass::options() as $option) {
            if ((int) ($option['id'] ?? 0) === (int) $id) {
                return (string) ($option['name'] ?? '');
            }
        }

        return '';
    }

    public function hullTypeLabel(?int $id): string
    {
        return $this->enumLabel($id, HullType::class);
    }

    public function hullMaterialLabel(?int $id): string
    {
        return $this->enumLabel($id, HullMaterial::class);
    }

    public function boatTypeLabel(?int $id): string
    {
        return $this->enumLabel($id, BoatType::class);
    }

    public function formatLengthMm(?int $mm): string
    {
        if ($mm === null || $mm <= 0) {
            return '';
        }

        $feet = round($mm / 304.8, 2);

        return $feet.' ft';
    }
}
