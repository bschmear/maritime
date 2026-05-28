<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Support;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Transaction\Models\Transaction;
use App\Enums\Invoice\Status as InvoiceStatus;

final class SyncLinkedDealTaxRate
{
    /**
     * Invoice statuses where tax must not change (customer has received the invoice).
     *
     * @return list<string>
     */
    public static function lockedInvoiceStatuses(): array
    {
        return [
            InvoiceStatus::Sent->value,
            InvoiceStatus::Viewed->value,
            InvoiceStatus::Partial->value,
            InvoiceStatus::Paid->value,
        ];
    }

    public static function invoiceIsTaxLocked(Invoice $invoice): bool
    {
        return in_array((string) $invoice->status, self::lockedInvoiceStatuses(), true);
    }

    public static function transactionHasSentInvoice(Transaction $transaction): bool
    {
        return $transaction->invoices()
            ->whereIn('status', self::lockedInvoiceStatuses())
            ->exists();
    }

    /**
     * Recalculate line tax from the deal rate and roll up transaction totals.
     */
    public static function applyRateToTransaction(Transaction $transaction, float $rate): void
    {
        $transaction->update([
            'tax_rate' => $rate > 0 ? round($rate, 3) : null,
        ]);

        $transaction->load(['items.addons', 'items.selectedAssetOptions']);

        foreach ($transaction->items as $item) {
            $qty = (float) $item->quantity;
            $price = (float) $item->unit_price;
            $discount = (float) $item->discount;
            $baseSubtotal = max(0, $qty * $price - $discount);
            $itemTaxable = ComputeTransactionLineTax::boolish($item->taxable);
            $itemTax = ComputeTransactionLineTax::amount($baseSubtotal, $itemTaxable, $rate);

            $addonsPreTax = 0.0;
            $addonsTaxSum = 0.0;

            foreach ($item->addons as $addon) {
                $aQty = (float) ($addon->quantity ?? 1);
                $aPrice = (float) ($addon->price ?? 0);
                $aBase = $aQty * $aPrice;
                $aTaxable = ComputeTransactionLineTax::boolish($addon->taxable ?? true);
                $aTax = ComputeTransactionLineTax::amount($aBase, $aTaxable, $rate);
                $addonsPreTax += $aBase;
                $addonsTaxSum += $aTax;

                $addon->update([
                    'tax_rate' => $rate > 0 ? $rate : null,
                    'tax_amount' => $aTax > 0 ? round($aTax, 2) : null,
                ]);
            }

            $item->update([
                'tax_rate' => $rate > 0 ? $rate : null,
                'tax_amount' => $itemTax > 0 ? round($itemTax, 2) : null,
            ]);

            RecalculateTransactionTotals::finalizeLineItem(
                $item->fresh(),
                $rate,
                $baseSubtotal,
                $itemTax,
                $addonsPreTax,
                $addonsTaxSum,
            );
        }

        RecalculateTransactionTotals::rollupTransaction($transaction->fresh());
    }

    /**
     * @return int Number of draft invoices updated
     */
    public static function applyRateToDraftInvoices(Transaction $transaction, float $rate): int
    {
        $updated = 0;

        $invoices = $transaction->invoices()
            ->whereNotIn('status', array_merge(self::lockedInvoiceStatuses(), [InvoiceStatus::Void->value]))
            ->with('items')
            ->get();

        foreach ($invoices as $invoice) {
            if (self::invoiceIsTaxLocked($invoice)) {
                continue;
            }

            foreach ($invoice->items as $item) {
                $item->update([
                    'tax_rate' => $item->taxable ? round($rate, 3) : 0,
                ]);
            }

            self::recalculateInvoiceTotals($invoice->fresh(['items']));
            $updated++;
        }

        return $updated;
    }

    public static function recalculateInvoiceTotals(Invoice $invoice): void
    {
        $invoice->loadMissing('items');

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($invoice->items as $item) {
            $qty = (float) $item->quantity;
            $price = (float) $item->unit_price;
            $discount = (float) $item->discount;
            $itemSub = ($qty * $price) - $discount;
            $subtotal += $itemSub;
            $discountTotal += $discount;

            if ($item->taxable && $item->tax_rate) {
                $taxTotal += round($itemSub * ((float) $item->tax_rate / 100), 2);
            }
        }

        $feesTotal = (float) ($invoice->fees_total ?? 0);
        $total = round($subtotal + $taxTotal + $feesTotal, 2);
        $amountDue = round(max(0, $total - (float) ($invoice->amount_paid ?? 0)), 2);

        $invoice->update([
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'discount_total' => round($discountTotal, 2),
            'total' => $total,
            'amount_due' => $amountDue,
        ]);
    }

    public static function resolveRateFromInvoiceItems(Invoice $invoice): float
    {
        $invoice->loadMissing('items');
        foreach ($invoice->items as $item) {
            if ($item->taxable && (float) ($item->tax_rate ?? 0) > 0) {
                return (float) $item->tax_rate;
            }
        }

        return 0.0;
    }
}
