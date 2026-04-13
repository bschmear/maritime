<?php

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentConfiguration;
use App\Services\Payments\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FulfillPublicInvoiceCheckoutSession
{
    public function __construct(private StripeService $stripe) {}

    /**
     * @return array{ok: bool, message?: string}
     */
    public function __invoke(Invoice $invoice, string $sessionId): array
    {
        if (Payment::query()->where('processor_transaction_id', $sessionId)->exists()) {
            return ['ok' => true];
        }

        $configuration = PaymentConfiguration::forStripe();

        try {
            $session = $this->stripe->retrieveCheckoutSession($configuration, $sessionId);
        } catch (\Throwable $e) {
            Log::warning('Invoice checkout session retrieve failed', [
                'invoice_id' => $invoice->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not verify payment. Please contact us if you were charged.'];
        }

        if ($session->client_reference_id !== $invoice->uuid) {
            return ['ok' => false, 'message' => 'Payment does not match this invoice.'];
        }

        $meta = $session->metadata ?? null;
        if ($meta === null || ($meta['invoice_id'] ?? null) !== (string) $invoice->id) {
            return ['ok' => false, 'message' => 'Payment does not match this invoice.'];
        }

        if ($session->payment_status !== 'paid') {
            return ['ok' => false, 'message' => 'Payment was not completed.'];
        }

        $principal = round((float) ($meta['principal'] ?? 0), 2);
        $surcharge = round((float) ($meta['surcharge'] ?? 0), 2);

        if ($principal <= 0) {
            return ['ok' => false, 'message' => 'Invalid payment amount.'];
        }

        $expectedTotalCents = (int) round(($principal + $surcharge) * 100);
        if (abs((int) $session->amount_total - $expectedTotalCents) > 1) {
            Log::error('Invoice checkout amount mismatch', [
                'invoice_id' => $invoice->id,
                'session_id' => $sessionId,
                'expected_cents' => $expectedTotalCents,
                'actual_cents' => $session->amount_total,
            ]);

            return ['ok' => false, 'message' => 'Payment amount mismatch. Please contact us.'];
        }

        try {
            DB::transaction(function () use ($invoice, $configuration, $session, $principal, $surcharge, $sessionId): void {
                $invoice->refresh();

                if (in_array($invoice->status, ['void', 'paid', 'draft'], true)) {
                    throw new \RuntimeException('invoice_locked');
                }

                if ($principal - (float) $invoice->amount_due > 0.02) {
                    throw new \RuntimeException('overpay');
                }

                $net = round($principal + $surcharge, 2);

                Payment::create([
                    'invoice_id' => $invoice->id,
                    'configuration_id' => $configuration->id,
                    'payment_method_code' => 'credit_card',
                    'status' => 'completed',
                    'amount' => $principal,
                    'surcharge_amount' => $surcharge,
                    'net_amount' => $net,
                    'currency' => strtoupper((string) ($invoice->currency ?: 'USD')),
                    'processor' => 'stripe',
                    'processor_transaction_id' => $sessionId,
                    'processor_status' => $session->payment_status,
                    'processor_response' => json_decode(json_encode($session), true),
                    'paid_at' => now(),
                ]);

                $invoice->applyPayment((float) $principal);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'invoice_locked') {
                return ['ok' => false, 'message' => 'This invoice cannot accept that payment.'];
            }
            if ($e->getMessage() === 'overpay') {
                return ['ok' => false, 'message' => 'That payment exceeds the balance due.'];
            }

            throw $e;
        } catch (\Throwable $e) {
            Log::error('Invoice checkout fulfillment failed', [
                'invoice_id' => $invoice->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not record payment. Please contact us.'];
        }

        return ['ok' => true];
    }
}
