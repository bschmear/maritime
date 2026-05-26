<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Support;

use App\Domain\Transaction\Models\Transaction;
use App\Enums\Deliveries\Status as DeliveryStatus;
use Illuminate\Validation\ValidationException;

final class AssertTransactionCanComplete
{
    /**
     * @throws ValidationException
     */
    public static function validate(Transaction $transaction): void
    {
        $transaction->loadMissing(['invoices', 'contract', 'serviceTickets', 'deliveries']);

        $errors = [];

        if ($transaction->needs_contract) {
            $status = $transaction->contract?->status;
            if ($status !== 'signed') {
                $errors['status'][] = 'Contract must be signed before marking this deal completed.';
            }
        }

        if ($transaction->needs_delivery) {
            $delivered = $transaction->deliveries->contains(
                fn ($d) => $d->status === DeliveryStatus::Delivered->value
            );
            if (! $delivered) {
                $errors['status'][] = 'At least one delivery must be marked delivered before completing this deal.';
            }
        }

        $invoices = $transaction->invoices ?? collect();
        $hasIssuedInvoice = $invoices->contains(
            fn ($inv) => ! in_array($inv->status ?? '', ['draft', 'void'], true)
        );
        if (! $hasIssuedInvoice) {
            $errors['status'][] = 'Create at least one invoice (not draft) before completing this deal.';
        }

        $tickets = $transaction->serviceTickets ?? collect();
        if ($tickets->isNotEmpty()) {
            $allClosed = $tickets->every(fn ($t) => in_array((int) ($t->status ?? 0), [4, 5], true));
            if (! $allClosed) {
                $errors['status'][] = 'All linked service tickets must be completed or closed before completing this deal.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
