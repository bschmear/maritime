<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\Estimate\Models\Estimate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EstimateSelectedOptionSync
{
    public function __construct(
        private PersistAssetOptionSelectionsForLineItem $persistSelections,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lineItemsData  Raw line_items payload (indexed by position).
     * @param  array<int, \App\Domain\Estimate\Models\EstimateLineItem>  $assetLineItemsByPosition  Asset line items keyed by position.
     * @param  array<int, array<string, mixed>>  $selectedAssetOptions  Groups with line_position + selections.
     */
    public function sync(
        Estimate $estimate,
        array $lineItemsData,
        array $assetLineItemsByPosition,
        array $selectedAssetOptions
    ): void {
        $validator = Validator::make(
            ['selected_asset_options' => $selectedAssetOptions],
            [
                'selected_asset_options' => ['nullable', 'array'],
                'selected_asset_options.*.line_position' => ['required', 'integer', 'min:0'],
                'selected_asset_options.*.selections' => ['nullable', 'array'],
                'selected_asset_options.*.selections.*.option_id' => ['required', 'integer'],
                'selected_asset_options.*.selections.*.option_value_id' => ['required', 'integer'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $selectionsByPosition = [];
        foreach ($selectedAssetOptions as $group) {
            $selectionsByPosition[(int) ($group['line_position'] ?? -1)] = $group['selections'] ?? [];
        }

        foreach ($lineItemsData as $position => $lineData) {
            if (($lineData['itemable_type'] ?? '') !== Asset::class) {
                continue;
            }

            $lineItem = $assetLineItemsByPosition[(int) $position] ?? null;
            if ($lineItem === null) {
                continue;
            }

            ($this->persistSelections)(
                $lineItem,
                $lineData,
                $selectionsByPosition[(int) $position] ?? [],
                $estimate->id,
                'boat line '.(((int) $position) + 1),
            );
        }
    }
}
