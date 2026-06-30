<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Models\EstimateSelectedOption;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\Transaction\Models\TransactionLineItem;
use App\Domain\Transaction\Support\ComputeTransactionLineTax;
use App\Services\AssetOptionResolver;
use Illuminate\Validation\ValidationException;

/**
 * Writes rows to {@see EstimateSelectedOption::$table} for a single {@link TransactionLineItem}.
 * That table is keyed only by {@code transaction_line_item_id} — the parent deal/estimate/etc.
 * lives on the polymorphic {@link TransactionLineItem} row.
 */
final class PersistAssetOptionSelectionsForLineItem
{
    public function __construct(private AssetOptionResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $lineData  itemable_type, itemable_id, asset_variant_id, asset_options_fill_mode
     * @param  array<int, array<string, mixed>>  $selections  list of option_id / option_value_id
     */
    public function __invoke(
        TransactionLineItem $lineItem,
        array $lineData,
        array $selections,
        ?int $estimateId = null,
        string $lineLabelForErrors = 'Line',
    ): void {
        if (($lineData['itemable_type'] ?? '') !== Asset::class) {
            return;
        }

        EstimateSelectedOption::query()
            ->where('transaction_line_item_id', $lineItem->id)
            ->delete();

        $asset = Asset::query()->find((int) ($lineData['itemable_id'] ?? 0));
        if ($asset === null) {
            return;
        }

        $customerFillsOptions = (($lineData['asset_options_fill_mode'] ?? 'staff') === 'customer');

        $variantId = ! empty($lineData['asset_variant_id']) ? (int) $lineData['asset_variant_id'] : null;
        $variant = $variantId
            ? AssetVariant::query()->whereKey($variantId)->where('asset_id', $asset->id)->first()
            : null;

        $assigned = $this->resolver->resolve($asset, $variant)->keyBy('option_id');

        $normalizedSelections = [];
        foreach ($selections as $sel) {
            if (! is_array($sel)) {
                continue;
            }
            $row = [
                'option_id' => (int) ($sel['option_id'] ?? 0),
                'option_value_id' => (int) ($sel['option_value_id'] ?? 0),
                'taxable' => ComputeTransactionLineTax::boolish($sel['taxable'] ?? true),
            ];
            if (array_key_exists('price', $sel)) {
                $row['price'] = max(0, (float) $sel['price']);
            }
            if (array_key_exists('cost', $sel)) {
                $row['cost'] = max(0, (float) $sel['cost']);
            }
            $normalizedSelections[] = $row;
        }

        $selectedByOption = [];
        foreach ($normalizedSelections as $sel) {
            $oid = $sel['option_id'];
            $vid = $sel['option_value_id'];
            if ($oid <= 0 || $vid <= 0) {
                continue;
            }
            if (! isset($selectedByOption[$oid])) {
                $selectedByOption[$oid] = [];
            }
            $selectedByOption[$oid][$vid] = $vid;
        }
        foreach ($selectedByOption as $oid => $vids) {
            $selectedByOption[$oid] = array_values($vids);
        }

        $selectedOptionIds = array_keys($selectedByOption);
        $globalAndSelected = $this->resolver->resolveByIds($asset, $variant, $selectedOptionIds)->keyBy('option_id');

        if ($customerFillsOptions) {
            $offeredIds = array_map('intval', $lineData['customer_offered_option_ids'] ?? []);
            if ($offeredIds !== []) {
                foreach ($selectedOptionIds as $oid) {
                    if (! in_array($oid, $offeredIds, true)) {
                        $name = (string) ($globalAndSelected->get($oid)['name'] ?? 'Unknown');
                        throw ValidationException::withMessages([
                            'selected_asset_options' => 'Option "'.$name.'" is not offered on this line.',
                        ]);
                    }
                }
            }
        }

        foreach ($assigned as $optionPayload) {
            $optionId = (int) $optionPayload['option_id'];
            $valueIds = $selectedByOption[$optionId] ?? [];

            if (! $customerFillsOptions && ($optionPayload['is_required'] ?? false) && $valueIds === []) {
                throw ValidationException::withMessages([
                    'selected_asset_options' => 'Option "'.$optionPayload['name'].'" is required for '.$lineLabelForErrors.'.',
                ]);
            }

            $this->validateSelectionCounts($optionPayload, $valueIds, $customerFillsOptions);
        }

        foreach ($normalizedSelections as $sel) {
            $oid = (int) $sel['option_id'];
            $vid = (int) $sel['option_value_id'];
            if ($oid <= 0 || $vid <= 0) {
                continue;
            }

            $optionPayload = $globalAndSelected->get($oid);
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
                'estimate_id' => $estimateId,
                'transaction_line_item_id' => $lineItem->id,
                'option_id' => $oid,
                'option_value_id' => $vid,
                'option_name' => $optionPayload['name'],
                'value_label' => $valueMeta['label'],
                'cost' => array_key_exists('cost', $sel)
                    ? $sel['cost']
                    : $valueMeta['cost'],
                'price' => array_key_exists('price', $sel)
                    ? $sel['price']
                    : $valueMeta['price'],
                'taxable' => $sel['taxable'] ?? true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $optionPayload
     * @param  list<int>  $valueIds
     */
    private function validateSelectionCounts(array $optionPayload, array $valueIds, bool $customerFillsOptions): void
    {
        $allowMultiple = (bool) ($optionPayload['allow_multiple'] ?? false);
        if (! $allowMultiple && count($valueIds) > 1) {
            throw ValidationException::withMessages([
                'selected_asset_options' => 'Option "'.$optionPayload['name'].'" allows only one selection.',
            ]);
        }

        $min = $optionPayload['min_select'] ?? null;
        $max = $optionPayload['max_select'] ?? null;
        $count = count($valueIds);
        if (! $customerFillsOptions && $min !== null && $count < $min) {
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
}
