<?php

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyManualInvoicePayment
{
    /**
     * Record a manual payment (check, cash, etc.) and apply it to the invoice balance.
     *
     * @param  array{amount: numeric-string|float|int, payment_method_code: string, reference_number?: ?string, memo?: ?string}  $validated
     */
    public function __invoke(Invoice $invoice, array $validated, ?int $recordedByUserId): void
    {
        $invoice->refresh();

        if (in_array($invoice->status, ['void', 'draft', 'paid'], true)) {
            throw ValidationException::withMessages([
                'amount' => ['This invoice cannot accept a payment in its current state.'],
            ]);
        }

        $principal = round((float) $validated['amount'], 2);
        $amountDue = round((float) $invoice->amount_due, 2);

        if ($principal > $amountDue + 0.01) {
            throw ValidationException::withMessages([
                'amount' => ['Amount cannot exceed the balance due.'],
            ]);
        }

        if (! $invoice->allow_partial_payment && abs($principal - $amountDue) > 0.02) {
            throw ValidationException::withMessages([
                'amount' => ['This invoice must be paid in full.'],
            ]);
        }

        if ($invoice->allow_partial_payment && $invoice->minimum_partial_amount !== null) {
            $min = round((float) $invoice->minimum_partial_amount, 2);
            if ($principal + 0.0001 < $min) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount must be at least '.number_format($min, 2).'.'],
                ]);
            }
        }

        DB::transaction(function () use ($invoice, $validated, $principal, $recordedByUserId): void {
            $invoice->refresh();

            if (in_array($invoice->status, ['void', 'draft', 'paid'], true)) {
                throw ValidationException::withMessages([
                    'amount' => ['This invoice cannot accept a payment in its current state.'],
                ]);
            }

            $due = round((float) $invoice->amount_due, 2);
            if ($principal > $due + 0.01) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount cannot exceed the balance due.'],
                ]);
            }

            $currency = strtoupper((string) ($invoice->currency ?: 'USD'));

            Payment::create([
                'invoice_id' => $invoice->id,
                'configuration_id' => null,
                'payment_method_code' => $validated['payment_method_code'],
                'status' => 'completed',
                'amount' => $principal,
                'surcharge_amount' => 0,
                'net_amount' => $principal,
                'currency' => $currency,
                'processor' => 'manual',
                'reference_number' => $validated['reference_number'] ?? null,
                'memo' => $validated['memo'] ?? null,
                'recorded_by_user_id' => $recordedByUserId,
                'paid_at' => now(),
            ]);

            $invoice->applyPayment($principal);
        });
    }
}
