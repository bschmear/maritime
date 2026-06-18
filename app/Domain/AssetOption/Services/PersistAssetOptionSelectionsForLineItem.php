<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Models\EstimateSelectedOption;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\Transaction\Models\TransactionLineItem;
use App\Domain\Transaction\Support\ComputeTransactionLineTax;
use App\Services\AssetOptionResolver;
use Illuminate\Support\Facades\Schema;
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

        $resolved = $this->resolver->resolve($asset, $variant)->keyBy('option_id');

        $normalizedSelections = [];
        foreach ($selections as $sel) {
            if (! is_array($sel)) {
                continue;
            }
            $normalizedSelections[] = [
                'option_id' => (int) ($sel['option_id'] ?? 0),
                'option_value_id' => (int) ($sel['option_value_id'] ?? 0),
                'taxable' => ComputeTransactionLineTax::boolish($sel['taxable'] ?? true),
            ];
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

        foreach ($resolved as $optionPayload) {
            $optionId = (int) $optionPayload['option_id'];
            $valueIds = $selectedByOption[$optionId] ?? [];

            if (! $customerFillsOptions && ($optionPayload['is_required'] ?? false) && $valueIds === []) {
                throw ValidationException::withMessages([
                    'selected_asset_options' => 'Option "'.$optionPayload['name'].'" is required for '.$lineLabelForErrors.'.',
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

        foreach ($normalizedSelections as $sel) {
            $oid = (int) $sel['option_id'];
            $vid = (int) $sel['option_value_id'];
            if ($oid <= 0 || $vid <= 0) {
                continue;
            }

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

            // #region agent log
            file_put_contents(
                base_path('.cursor/debug-ae1c12.log'),
                json_encode([
                    'sessionId' => 'ae1c12',
                    'hypothesisId' => 'H1',
                    'location' => 'PersistAssetOptionSelectionsForLineItem.php:before-create',
                    'message' => 'selected option insert schema check',
                    'data' => [
                        'tenant_id' => tenant()?->id,
                        'line_item_id' => $lineItem->id,
                        'has_taxable_column' => Schema::hasColumn('transaction_line_item_selected_options', 'taxable'),
                    ],
                    'timestamp' => (int) round(microtime(true) * 1000),
                ])."\n",
                FILE_APPEND
            );
            // #endregion

            EstimateSelectedOption::query()->create([
                'estimate_id' => $estimateId,
                'transaction_line_item_id' => $lineItem->id,
                'option_id' => $oid,
                'option_value_id' => $vid,
                'option_name' => $optionPayload['name'],
                'value_label' => $valueMeta['label'],
                'cost' => $valueMeta['cost'],
                'price' => $valueMeta['price'],
                'taxable' => $sel['taxable'] ?? true,
            ]);
        }
    }
}
