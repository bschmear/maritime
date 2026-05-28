<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Support;

use App\Domain\Transaction\Models\Transaction;
use App\Enums\Invoice\Status as InvoiceStatus;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AssertTransactionLineItemsEditable
{
    /**
     * @return Collection<int, \App\Domain\Invoice\Models\Invoice>
     */
    public static function blockingInvoices(Transaction $transaction): Collection
    {
        return $transaction->invoices()
            ->where('status', '!=', InvoiceStatus::Void->value)
            ->orderByDesc('id')
            ->get();
    }

    public static function lineItemsAreLocked(Transaction $transaction): bool
    {
        return self::blockingInvoices($transaction)->isNotEmpty();
    }

    public static function validate(Transaction $transaction): void
    {
        $blocking = self::blockingInvoices($transaction);
        if ($blocking->isEmpty()) {
            return;
        }

        $hasPaid = $blocking->contains(function ($invoice) {
            if ((string) $invoice->status === InvoiceStatus::Paid->value) {
                return true;
            }

            return (float) ($invoice->amount_paid ?? 0) > 0;
        });

        $message = $hasPaid
            ? 'Line items cannot be changed while this deal has a paid invoice. Adjust or void the invoice before editing deal line items.'
            : 'Line items cannot be changed while an invoice exists on this deal. Delete or void the invoice first, then edit line items.';

        throw ValidationException::withMessages([
            'items' => $message,
        ]);
    }
}
