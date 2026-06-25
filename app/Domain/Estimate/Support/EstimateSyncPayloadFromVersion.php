<?php

declare(strict_types=1);

namespace App\Domain\Estimate\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Estimate\Models\EstimateLineItem;
use App\Domain\Estimate\Models\EstimateVersion;

/**
 * Rebuilds payloads needed by {@see \App\Domain\AssetOption\Services\EstimateSelectedOptionSync}
 * from the persisted estimate version (used by the customer boat-options flow).
 */
final class EstimateSyncPayloadFromVersion
{
    /**
     * @return array{
     *     line_items: array<int, array<string, mixed>>,
     *     asset_line_items_by_position: array<int, EstimateLineItem>,
     *     selected_asset_options: array<int, array{line_position: int, selections: array<int, array{option_id: int, option_value_id: int}>}>
     * }
     */
    public static function forSelectedOptionSync(Estimate $estimate): array
    {
        $estimate->loadMissing([
            'selectedAssetOptions',
            'primaryVersion.lineItems',
        ]);

        $version = $estimate->primaryVersion;
        if ($version === null) {
            return [
                'line_items' => [],
                'asset_line_items_by_position' => [],
                'selected_asset_options' => [],
            ];
        }

        $lineItemsData = self::lineItemsData($version);
        $assetLineItemsByPosition = self::assetLineItemsByPosition($version);

        $byLineId = $estimate->selectedAssetOptions->groupBy('transaction_line_item_id');
        $selectedAssetOptions = [];

        foreach ($version->lineItems->sortBy('position') as $li) {
            if (($li->itemable_type ?? '') !== Asset::class) {
                continue;
            }
            $selections = ($byLineId->get($li->id) ?? collect())->map(fn ($s) => [
                'option_id' => (int) $s->option_id,
                'option_value_id' => (int) $s->option_value_id,
            ])->values()->all();

            $selectedAssetOptions[] = [
                'line_position' => (int) $li->position,
                'selections' => $selections,
            ];
        }

        return [
            'line_items' => $lineItemsData,
            'asset_line_items_by_position' => $assetLineItemsByPosition,
            'selected_asset_options' => $selectedAssetOptions,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function lineItemsData(EstimateVersion $version): array
    {
        $lineItemsData = [];

        foreach ($version->lineItems->sortBy('position') as $li) {
            $pos = (int) $li->position;
            $lineItemsData[$pos] = [
                'itemable_type' => $li->itemable_type,
                'itemable_id' => $li->itemable_id,
                'asset_variant_id' => $li->asset_variant_id,
                'asset_unit_id' => $li->asset_unit_id,
                'asset_options_fill_mode' => $li->asset_options_fill_mode ?? 'staff',
                'customer_offered_option_ids' => $li->customer_offered_option_ids,
            ];
        }

        return $lineItemsData;
    }

    /**
     * @return array<int, EstimateLineItem>
     */
    public static function assetLineItemsByPosition(EstimateVersion $version): array
    {
        $map = [];

        foreach ($version->lineItems->sortBy('position') as $li) {
            if (($li->itemable_type ?? '') !== Asset::class) {
                continue;
            }
            $map[(int) $li->position] = $li;
        }

        return $map;
    }
}
