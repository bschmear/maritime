<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Support;

use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionLineItem;

final class RecalculateTransactionTotals
{
    /**
     * After addons and {@see transaction_line_item_selected_options} rows exist, store full
     * pre-tax subtotal (base + add-ons + boat options), combined tax, and line total.
     */
    public static function finalizeLineItem(
        TransactionLineItem $item,
        float $dealRate,
        float $baseSubtotal,
        float $itemTax,
        float $addonsPreTax,
        float $addonsTaxSum,
    ): void {
        $item->refresh();
        $optionsPreTax = (float) $item->selectedAssetOptions()->sum('price');
        $optionsTaxSum = 0.0;
        foreach ($item->selectedAssetOptions as $optRow) {
            $optionsTaxSum += ComputeTransactionLineTax::amount(
                (float) ($optRow->price ?? 0),
                ComputeTransactionLineTax::boolish($optRow->taxable ?? true),
                $dealRate
            );
        }

        $linePreTax = $baseSubtotal + $addonsPreTax + $optionsPreTax;
        $allTax = $itemTax + $addonsTaxSum + $optionsTaxSum;

        $item->update([
            'subtotal' => round($linePreTax, 2),
            'tax_amount' => $allTax > 0 ? round($allTax, 2) : null,
            'total' => round($linePreTax + $allTax, 2),
        ]);
    }

    /**
     * Sum finalized line rows into the parent transaction (pre-tax, tax, grand total).
     */
    public static function rollupTransaction(Transaction $transaction): void
    {
        $transaction->load('items');

        $subtotal = (float) $transaction->items->sum('subtotal');
        $taxTotal = (float) $transaction->items->sum(fn ($i) => (float) ($i->tax_amount ?? 0));
        $discount = floatval($transaction->discount_total ?? 0);
        $fees = floatval($transaction->fees_total ?? 0);

        $transaction->update([
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($subtotal + $taxTotal - $discount + $fees, 2),
        ]);
    }
}
