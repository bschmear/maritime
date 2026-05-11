<?php

declare(strict_types=1);

namespace App\Domain\Estimate\Support;

use App\Domain\AssetOption\Models\EstimateSelectedOption;
use App\Domain\Estimate\Models\EstimateVersion;

final class RecalculateEstimateVersionTotals
{
    /**
     * After asset option rows are synced, roll option premiums into each line's line_total and refresh version totals.
     */
    public static function apply(EstimateVersion $version, float $taxRatePercent): void
    {
        $version->loadMissing(['lineItems.addons']);

        foreach ($version->lineItems as $li) {
            $base = (float) $li->unit_price * (int) $li->quantity - (float) $li->discount;
            $base = max(0, round($base, 2));

            $optionSum = (float) EstimateSelectedOption::query()
                ->where('transaction_line_item_id', $li->id)
                ->sum('price');

            $li->update([
                'line_total' => round($base + $optionSum, 2),
            ]);
        }

        $version->refresh();
        $version->load(['lineItems.addons']);

        $subtotal = 0.0;
        foreach ($version->lineItems as $li) {
            $subtotal += (float) $li->line_total;
            foreach ($li->addons as $addon) {
                $subtotal += (float) $addon->price * (int) $addon->quantity;
            }
        }

        $subtotal = round($subtotal, 2);
        $tax = round($subtotal * ($taxRatePercent / 100), 2);
        $total = round($subtotal + $tax, 2);

        $version->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ]);
    }
}
