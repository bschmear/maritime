<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Models\EstimateSelectedOption;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Estimate\Models\EstimateLineItem;
use App\Services\AssetOptionResolver;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EstimateSelectedOptionSync
{
    public function __construct(private AssetOptionResolver $resolver) {}

    /**
     * @param  array<int, array<string, mixed>>  $lineItemsData  Raw line_items payload (indexed by position).
     * @param  array<int, EstimateLineItem>  $assetLineItemsByPosition  Asset line items keyed by position.
     * @param  array<int, array<string, mixed>>  $selectedAssetOptions  Groups with line_position + selections.
     */
    public function sync(
        Estimate $estimate,
        array $lineItemsData,
        array $assetLineItemsByPosition,
        array $selectedAssetOptions
    ): void {
        EstimateSelectedOption::query()->where('estimate_id', $estimate->id)->delete();

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

        foreach ($selectedAssetOptions as $group) {
            $pos = (int) ($group['line_position'] ?? -1);
            $lineItem = $assetLineItemsByPosition[$pos] ?? null;
            $lineData = $lineItemsData[$pos] ?? null;

            if ($lineItem === null || $lineData === null) {
                continue;
            }

            if (($lineData['itemable_type'] ?? '') !== Asset::class) {
                continue;
            }

            $asset = Asset::query()->find((int) ($lineData['itemable_id'] ?? 0));
            if ($asset === null) {
                continue;
            }

            $variantId = ! empty($lineData['asset_variant_id']) ? (int) $lineData['asset_variant_id'] : null;
            $variant = $variantId
                ? AssetVariant::query()->whereKey($variantId)->where('asset_id', $asset->id)->first()
                : null;

            $resolved = $this->resolver->resolve($asset, $variant)->keyBy('option_id');

            $selections = $group['selections'] ?? [];
            $selectedByOption = [];
            foreach ($selections as $sel) {
                $oid = (int) $sel['option_id'];
                $vid = (int) $sel['option_value_id'];
                if (! isset($selectedByOption[$oid])) {
                    $selectedByOption[$oid] = [];
                }
                $selectedByOption[$oid][$vid] = $vid;
            }

            foreach ($selectedByOption as $oid => $vids) {
                $selectedByOption[$oid] = array_values($vids);
            }

            foreach ($resolved as $optionPayload) {
                $optionId = (int) $optionPayload['option_id'];
                $valueIds = $selectedByOption[$optionId] ?? [];

                if (($optionPayload['is_required'] ?? false) && $valueIds === []) {
                    throw ValidationException::withMessages([
                        'selected_asset_options' => 'Option "'.$optionPayload['name'].'" is required for boat line '.($pos + 1).'.',
                    ]);
                }

                $allowMultiple = (bool) ($optionPayload['allow_multiple'] ?? false);
                if (! $allowMultiple && count($valueIds) > 1) {
                    throw ValidationException::withMessages([
                        'selected_asset_options' => 'Option "'.$optionPayload['name'].'" allows only one selection.',
                    ]);
                }

                $min = $optionPayload['min_select'] ?? null;
                $max = $optionPayload['max_select'] ?? null;
                $count = count($valueIds);
                if ($min !== null && $count < $min) {
                    throw ValidationException::withMessages([
                        'selected_asset_options' => 'Option "'.$optionPayload['name'].'" requires at least '.$min.' selection(s).',
                    ]);
                }
                if ($max !== null && $count > $max) {
                    throw ValidationException::withMessages([
                        'selected_asset_options' => 'Option "'.$optionPayload['name'].'" allows at most '.$max.' selection(s).',
                    ]);
                }
            }

            foreach ($selections as $sel) {
                $oid = (int) $sel['option_id'];
                $vid = (int) $sel['option_value_id'];

                $optionPayload = $resolved->get($oid);
                if ($optionPayload === null) {
                    throw ValidationException::withMessages([
                        'selected_asset_options' => 'Invalid option selection for this boat configuration.',
                    ]);
                }

                $valueMeta = collect($optionPayload['values'] ?? [])->firstWhere('id', $vid);
                if ($valueMeta === null) {
                    throw ValidationException::withMessages([
                        'selected_asset_options' => 'Invalid option value for "'.$optionPayload['name'].'".',
                    ]);
                }

                EstimateSelectedOption::query()->create([
                    'estimate_id' => $estimate->id,
                    'estimate_line_item_id' => $lineItem->id,
                    'option_id' => $oid,
                    'option_value_id' => $vid,
                    'option_name' => $optionPayload['name'],
                    'value_label' => $valueMeta['label'],
                    'cost' => $valueMeta['cost'],
                    'price' => $valueMeta['price'],
                ]);
            }
        }
    }
}
