<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Enums\Payments\PaymentMethod;
use App\Enums\Payments\PaymentProcessor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreRecordedPayment
{
    /**
     * Log a payment (optionally updating the invoice balance).
     *
     * @param  array{
     *     invoice_id: int,
     *     amount: numeric-string|float|int,
     *     payment_method_code: string,
     *     processor: string,
     *     reference_number?: ?string,
     *     memo?: ?string,
     *     paid_at?: ?string,
     *     apply_to_invoice?: bool|int|string|null
     * }  $validated
     */
    public function __invoke(array $validated, ?int $recordedByUserId): Payment
    {
        $apply = filter_var($validated['apply_to_invoice'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $method = PaymentMethod::tryFrom((string) $validated['payment_method_code'])
            ?? throw ValidationException::withMessages([
                'payment_method_code' => ['Invalid payment method.'],
            ]);

        $processor = PaymentProcessor::tryFrom((string) $validated['processor'])
            ?? throw ValidationException::withMessages([
                'processor' => ['Invalid processor.'],
            ]);

        $invoice = Invoice::query()
            ->open()
            ->whereKey((int) $validated['invoice_id'])
            ->first();

        if ($invoice === null) {
            throw ValidationException::withMessages([
                'invoice_id' => ['The invoice must be sent, viewed, or partially paid (not draft, paid, or void).'],
            ]);
        }

        $principal = round((float) $validated['amount'], 2);
        $paidAt = isset($validated['paid_at']) && $validated['paid_at'] !== ''
            ? Carbon::parse((string) $validated['paid_at'])
            : now();

        if ($apply) {
            return $this->storeAndApply($invoice, $principal, $method->value, $processor->value, $validated, $recordedByUserId, $paidAt);
        }

        $currency = strtoupper((string) ($invoice->currency ?: 'USD'));

        return Payment::create([
            'invoice_id' => $invoice->id,
            'configuration_id' => null,
            'payment_method_code' => $method->value,
            'status' => 'completed',
            'amount' => $principal,
            'surcharge_amount' => 0,
            'net_amount' => $principal,
            'currency' => $currency,
            'processor' => $processor->value,
            'reference_number' => $validated['reference_number'] ?? null,
            'memo' => $validated['memo'] ?? null,
            'recorded_by_user_id' => $recordedByUserId,
            'paid_at' => $paidAt,
        ]);
    }

    /**
     * @param  array{reference_number?: ?string, memo?: ?string}  $validated
     */
    private function storeAndApply(
        Invoice $invoice,
        float $principal,
        string $paymentMethodCode,
        string $processor,
        array $validated,
        ?int $recordedByUserId,
        Carbon $paidAt,
    ): Payment {
        $invoice->refresh();

        if (in_array($invoice->status, ['void', 'draft', 'paid'], true)) {
            throw ValidationException::withMessages([
                'amount' => ['This invoice cannot accept a payment in its current state.'],
            ]);
        }

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

        return DB::transaction(function () use (
            $invoice,
            $principal,
            $paymentMethodCode,
            $processor,
            $validated,
            $recordedByUserId,
            $paidAt,
        ): Payment {
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

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'configuration_id' => null,
                'payment_method_code' => $paymentMethodCode,
                'status' => 'completed',
                'amount' => $principal,
                'surcharge_amount' => 0,
                'net_amount' => $principal,
                'currency' => $currency,
                'processor' => $processor,
                'reference_number' => $validated['reference_number'] ?? null,
                'memo' => $validated['memo'] ?? null,
                'recorded_by_user_id' => $recordedByUserId,
                'paid_at' => $paidAt,
            ]);

            $invoice->applyPayment($principal);

            return $payment;
        });
    }
}
