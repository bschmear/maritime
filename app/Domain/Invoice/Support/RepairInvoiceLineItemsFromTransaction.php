<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Support;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Transaction\Models\Transaction;

final class RepairInvoiceLineItemsFromTransaction
{
    public static function repairIfNeeded(Invoice $invoice): bool
    {
        if (! $invoice->transaction_id) {
            return false;
        }

        $transaction = Transaction::query()->find($invoice->transaction_id);
        if (! $transaction) {
            return false;
        }

        $expectedItems = FlattenTransactionItemsForInvoice::fromTransaction($transaction);
        $expected = FlattenTransactionItemsForInvoice::rollupTotals(
            $expectedItems,
            (float) ($invoice->fees_total ?? 0),
        );

        $invoice->loadMissing('items');
        $actualSubtotal = round((float) $invoice->items->sum('subtotal'), 2);
        $actualTax = round((float) $invoice->items->sum('tax_amount'), 2);

        if (
            abs($actualSubtotal - $expected['subtotal']) < 0.01
            && abs($actualTax - $expected['tax_total']) < 0.01
        ) {
            return false;
        }

        ReplaceInvoiceLineItems::apply($invoice, $expectedItems);

        $amountPaid = (float) $invoice->amount_paid;
        $invoice->update([
            'subtotal' => $expected['subtotal'],
            'tax_total' => $expected['tax_total'],
            'discount_total' => $expected['discount_total'],
            'total' => $expected['total'],
            'amount_due' => round(max(0, $expected['total'] - $amountPaid), 2),
        ]);

        return true;
    }
}
