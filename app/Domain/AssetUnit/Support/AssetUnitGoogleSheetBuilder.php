<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;
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
        $rows = [$this->headers()];

        foreach ($units as $unit) {
            $rows[] = $this->rowForUnit($unit);
        }

        return $rows;
    }

    /**
     * @return array{
     *   status: list<string>,
     *   condition: list<string>,
     *   makes: list<string>,
     *   variants: list<string>,
     *   locations: list<string>,
     *   subsidiaries: list<string>
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
            ->with(['asset:id,display_name,model'])
            ->orderBy('display_name')
            ->get()
            ->map(function (AssetVariant $variant) {
                $assetModel = $variant->asset?->model ?: $variant->asset?->display_name ?: 'Asset #'.$variant->asset_id;

                return trim($assetModel.' — '.($variant->display_name ?: $variant->name));
            })
            ->filter()
            ->values()
            ->all();

        $locations = Location::query()
            ->orderBy('display_name')
            ->pluck('display_name')
            ->filter()
            ->values()
            ->all();

        $subsidiaries = Subsidiary::query()
            ->orderBy('display_name')
            ->pluck('display_name')
            ->filter()
            ->values()
            ->all();

        return [
            'status' => $this->columns->statusLabels(),
            'condition' => $this->columns->conditionLabels(),
            'makes' => $makes,
            'variants' => $variants,
            'locations' => $locations,
            'subsidiaries' => $subsidiaries,
        ];
    }

    /**
     * @return list<mixed>
     */
    private function rowForUnit(AssetUnit $unit): array
    {
        $asset = $unit->asset;
        $variant = $unit->assetVariant;

        return [
            $asset?->make?->display_name ?? '',
            $asset?->model ?? '',
            $variant ? ($variant->display_name ?: $variant->name) : '',
            $this->statusLabel($unit->status),
            $this->conditionLabel($unit->condition),
            $unit->hin ?? '',
            $unit->serial_number ?? '',
            $unit->year ?? '',
            $unit->cost,
            $unit->asking_price,
            $unit->location?->display_name ?? '',
            $unit->subsidiary?->display_name ?? '',
        ];
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
}
