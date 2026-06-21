<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;
use Illuminate\Support\Collection;

class AssetUnitGoogleSheetBuilder
{
    public function __construct(
        private readonly AssetUnitGoogleSheetColumnRegistry $columns = new AssetUnitGoogleSheetColumnRegistry,
    ) {}

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return $this->columns->allHeaders();
    }

    /**
     * @param  Collection<int, AssetUnit>  $units
     * @return list<list<mixed>>
     */
    public function buildInventoryRows(Collection $units): array
    {
        $specDefinitions = $this->columns->specDefinitions();
        $specIds = array_map(fn (AssetSpecDefinition $d) => $d->id, $specDefinitions);

        $specValuesBySpecable = $this->loadSpecValues($units, $specIds);

        $rows = [$this->headers()];

        foreach ($units as $unit) {
            $rows[] = $this->rowForUnit($unit, $specDefinitions, $specValuesBySpecable);
        }

        return $rows;
    }

    /**
     * @return array{
     *   status: list<string>,
     *   condition: list<string>,
     *   makes: list<string>,
     *   variants: list<string>
     * }
     */
    public function referenceLists(): array
    {
        $makes = BoatMake::query()
            ->orderBy('display_name')
            ->pluck('display_name')
            ->filter()
            ->values()
            ->all();

        $variants = AssetVariant::query()
            ->with(['asset:id,display_name'])
            ->orderBy('display_name')
            ->get()
            ->map(function (AssetVariant $variant) {
                $assetName = $variant->asset?->display_name ?? 'Asset #'.$variant->asset_id;

                return trim($assetName.' — '.($variant->display_name ?: $variant->name));
            })
            ->filter()
            ->values()
            ->all();

        return [
            'status' => $this->columns->statusLabels(),
            'condition' => $this->columns->conditionLabels(),
            'makes' => $makes,
            'variants' => $variants,
        ];
    }

    /**
     * @param  list<AssetSpecDefinition>  $specDefinitions
     * @param  array<string, array<int, AssetSpecValue>>  $specValuesBySpecable
     * @return list<mixed>
     */
    private function rowForUnit(
        AssetUnit $unit,
        array $specDefinitions,
        array $specValuesBySpecable,
    ): array {
        $asset = $unit->asset;
        $variant = $unit->assetVariant;
        $specableKey = $this->specableKey($unit);
        $specValues = $specValuesBySpecable[$specableKey] ?? [];

        $source = $variant ?? $asset;

        $row = [
            $unit->id,
            $unit->serial_number ?? '',
            $unit->hin ?? '',
            $unit->sku ?? '',
            $asset?->display_name ?? '',
            $asset?->make?->display_name ?? '',
            $variant ? ($variant->display_name ?: $variant->name) : '',
            $unit->year ?? '',
            $this->statusLabel($unit->status),
            $this->conditionLabel($unit->condition),
            $unit->cost,
            $unit->asking_price,
            $unit->location?->display_name ?? '',
            $asset?->model ?? '',
            $asset?->year ?? '',
            $this->columns->formatLengthMm($source?->length),
            $this->columns->formatLengthMm($source?->width),
            $this->columns->hullTypeLabel($source?->hull_type),
            $this->columns->hullMaterialLabel($source?->hull_material),
            $this->columns->boatTypeLabel($source?->boat_type),
            $source?->maximum_power ?? '',
        ];

        foreach ($specDefinitions as $definition) {
            $row[] = $this->formatSpecValue($specValues[$definition->id] ?? null, $definition);
        }

        return $row;
    }

    private function statusLabel(?int $statusId): string
    {
        foreach (UnitStatus::options() as $option) {
            if ((int) $option['id'] === (int) $statusId) {
                return (string) $option['name'];
            }
        }

        return '';
    }

    private function conditionLabel(?int $conditionId): string
    {
        foreach (UnitCondition::options() as $option) {
            if ((int) $option['id'] === (int) $conditionId) {
                return (string) $option['name'];
            }
        }

        return '';
    }

    private function formatSpecValue(?AssetSpecValue $value, AssetSpecDefinition $definition): string
    {
        if ($value === null) {
            return '';
        }

        return match ($definition->type) {
            'boolean' => $value->value_boolean ? 'Yes' : 'No',
            'number' => $value->value_number !== null ? (string) $value->value_number : '',
            default => (string) ($value->value_text ?? ''),
        };
    }

    /**
     * @param  Collection<int, AssetUnit>  $units
     * @param  list<int>  $specIds
     * @return array<string, array<int, AssetSpecValue>>
     */
    private function loadSpecValues(Collection $units, array $specIds): array
    {
        if ($specIds === []) {
            return [];
        }

        $specables = [];
        foreach ($units as $unit) {
            if ($unit->asset_variant_id && $unit->assetVariant) {
                $specables[] = [$unit->assetVariant->getMorphClass(), $unit->asset_variant_id];
            } elseif ($unit->asset) {
                $specables[] = [$unit->asset->getMorphClass(), $unit->asset_id];
            }
        }

        if ($specables === []) {
            return [];
        }

        $values = AssetSpecValue::query()
            ->whereIn('asset_spec_definition_id', $specIds)
            ->where(function ($q) use ($specables) {
                foreach ($specables as [$type, $id]) {
                    $q->orWhere(function ($inner) use ($type, $id) {
                        $inner->where('specable_type', $type)->where('specable_id', $id);
                    });
                }
            })
            ->get();

        $grouped = [];
        foreach ($values as $value) {
            $key = $value->specable_type.':'.$value->specable_id;
            $grouped[$key][$value->asset_spec_definition_id] = $value;
        }

        $byUnit = [];
        foreach ($units as $unit) {
            $byUnit[$this->specableKey($unit)] = $grouped[$this->specableKey($unit)] ?? [];
        }

        return $byUnit;
    }

    private function specableKey(AssetUnit $unit): string
    {
        if ($unit->asset_variant_id && $unit->assetVariant) {
            return $unit->assetVariant->getMorphClass().':'.$unit->asset_variant_id;
        }

        if ($unit->asset) {
            return $unit->asset->getMorphClass().':'.$unit->asset_id;
        }

        return 'none:0';
    }
}
