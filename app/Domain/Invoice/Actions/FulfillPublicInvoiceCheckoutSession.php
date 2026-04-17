<?php

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentConfiguration;
use App\Enums\Payments\PaymentMethod;
use App\Services\Payments\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;

class FulfillPublicInvoiceCheckoutSession
{
    public function __construct(private StripeService $stripe) {}

    /**
     * @return array{ok: bool, message?: string, status?: string, checkout_refresh?: bool}
     *
     * `status` values: `paid`, `processing`, or `failed` (only set when {@code ok} is false).
     */
    public function __invoke(Invoice $invoice, string $sessionId): array
    {
        $configuration = PaymentConfiguration::forStripe();

        try {
            $session = $this->stripe->retrieveCheckoutSession($configuration, $sessionId, [
                'payment_intent.payment_method',
                'payment_intent.latest_charge',
            ]);
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

        $maxPasses = 4;
        for ($pass = 1; $pass <= $maxPasses; $pass++) {
            if ($pass > 1) {
                usleep(350_000);
                try {
                    $session = $this->stripe->retrieveCheckoutSession($configuration, $sessionId, [
                        'payment_intent.payment_method',
                        'payment_intent.latest_charge',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Invoice checkout session re-retrieve failed', [
                        'invoice_id' => $invoice->id,
                        'session_id' => $sessionId,
                        'pass' => $pass,
                        'error' => $e->getMessage(),
                    ]);

                    break;
                }

                if (abs((int) $session->amount_total - $expectedTotalCents) > 1) {
                    break;
                }
            }

            $this->hydrateCheckoutSessionPaymentIntent($configuration, $session);

            $paymentStatus = (string) ($session->payment_status ?? '');
            $intent = $session->payment_intent ?? null;
            $piStatus = is_object($intent) ? (string) ($intent->status ?? '') : '';

            // Session can lag the PaymentIntent; PI `succeeded` still means the customer paid.
            if ($paymentStatus === 'paid' || $piStatus === 'succeeded') {
                return $this->recordCompletedPayment($invoice, $configuration, $session, $principal, $surcharge, $sessionId);
            }

            if ($this->checkoutSessionIsBankProcessing($paymentStatus, $piStatus)) {
                return $this->recordProcessingPayment($invoice, $configuration, $session, $principal, $surcharge, $sessionId);
            }

            if ($pass < $maxPasses && $this->shouldPollStripeCheckoutSession($paymentStatus, $piStatus)) {
                continue;
            }

            break;
        }

        return [
            'ok' => false,
            'message' => 'We could not confirm your payment yet. If you finished checkout, wait a few seconds and refresh this page. Contact us if this keeps happening.',
            'status' => 'failed',
            'checkout_refresh' => true,
        ];
    }

    private function hydrateCheckoutSessionPaymentIntent(PaymentConfiguration $configuration, Session $session): void
    {
        $pi = $session->payment_intent ?? null;
        if (! is_string($pi) || ! str_starts_with($pi, 'pi_')) {
            return;
        }

        try {
            $session->payment_intent = $this->stripe->retrievePaymentIntent($configuration, $pi, [
                'payment_method',
                'latest_charge',
            ]);
        } catch (\Throwable) {
            // Leave as id string; status resolution may retry on a later poll pass.
        }
    }

    /**
     * ACH / bank debits: Checkout often stays `unpaid` while the debit is in flight.
     *
     * @see https://stripe.com/docs/payments/paymentintents/lifecycle
     */
    private function checkoutSessionIsBankProcessing(string $paymentStatus, string $piStatus): bool
    {
        if ($paymentStatus !== 'unpaid') {
            return false;
        }

        return in_array($piStatus, [
            'processing',
            'requires_action',
            'requires_confirmation',
            'requires_capture',
        ], true);
    }

    private function shouldPollStripeCheckoutSession(string $paymentStatus, string $piStatus): bool
    {
        if ($paymentStatus !== 'unpaid') {
            return false;
        }

        if ($piStatus === '') {
            return true;
        }

        return in_array($piStatus, ['requires_confirmation', 'requires_action'], true);
    }

    /**
     * @return array{ok: bool, message?: string, status?: string}
     */
    private function recordCompletedPayment(
        Invoice $invoice,
        PaymentConfiguration $configuration,
        Session $session,
        float $principal,
        float $surcharge,
        string $sessionId,
    ): array {
        try {
            DB::transaction(function () use ($invoice, $configuration, $session, $principal, $surcharge, $sessionId): void {
                $invoice->refresh();

                if (in_array($invoice->status, ['void', 'draft'], true)) {
                    throw new \RuntimeException('invoice_locked');
                }

                $existing = Payment::query()
                    ->where('processor_transaction_id', $sessionId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null && $existing->status === 'completed') {
                    return;
                }

                if ($principal - (float) $invoice->amount_due > 0.02) {
                    throw new \RuntimeException('overpay');
                }

                $net = round($principal + $surcharge, 2);
                $referenceNumber = self::stripeCheckoutReferenceNumber($session);
                $attributes = [
                    'invoice_id' => $invoice->id,
                    'configuration_id' => $configuration->id,
                    'payment_method_code' => $this->resolveCheckoutPaymentMethodCode($configuration, $session),
                    'status' => 'completed',
                    'amount' => $principal,
                    'surcharge_amount' => $surcharge,
                    'net_amount' => $net,
                    'currency' => strtoupper((string) ($invoice->currency ?: 'USD')),
                    'processor' => 'stripe',
                    'processor_transaction_id' => $sessionId,
                    'reference_number' => $referenceNumber,
                    'processor_status' => $session->payment_status,
                    'processor_response' => json_decode(json_encode($session), true),
                    'paid_at' => now(),
                ];

                if ($existing !== null) {
                    $existing->update($attributes);
                } else {
                    Payment::create($attributes);
                }

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

        return ['ok' => true, 'status' => 'paid'];
    }

    /**
     * @return array{ok: bool, message?: string, status?: string}
     */
    private function recordProcessingPayment(
        Invoice $invoice,
        PaymentConfiguration $configuration,
        Session $session,
        float $principal,
        float $surcharge,
        string $sessionId,
    ): array {
        try {
            DB::transaction(function () use ($invoice, $configuration, $session, $principal, $surcharge, $sessionId): void {
                $existing = Payment::query()
                    ->where('processor_transaction_id', $sessionId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return;
                }

                Payment::create([
                    'invoice_id' => $invoice->id,
                    'configuration_id' => $configuration->id,
                    'payment_method_code' => $this->resolveCheckoutPaymentMethodCode($configuration, $session),
                    'status' => 'processing',
                    'amount' => $principal,
                    'surcharge_amount' => $surcharge,
                    'net_amount' => round($principal + $surcharge, 2),
                    'currency' => strtoupper((string) ($invoice->currency ?: 'USD')),
                    'processor' => 'stripe',
                    'processor_transaction_id' => $sessionId,
                    'reference_number' => self::stripeCheckoutReferenceNumber($session),
                    'processor_status' => $session->payment_status,
                    'processor_response' => json_decode(json_encode($session), true),
                    'paid_at' => null,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Invoice checkout processing-record failed', [
                'invoice_id' => $invoice->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not record your pending payment. Please contact us if you were charged.'];
        }

        return [
            'ok' => false,
            'status' => 'processing',
            'message' => 'Your bank payment is processing. Your balance will update when the transfer clears (often 1–4 business days). We have recorded a pending payment on this invoice.',
        ];
    }

    public function markSessionFailed(Invoice $invoice, Session $session, string $sessionId, ?string $failureMessage = null): void
    {
        DB::transaction(function () use ($invoice, $session, $sessionId, $failureMessage): void {
            $existing = Payment::query()
                ->where('processor_transaction_id', $sessionId)
                ->lockForUpdate()
                ->first();

            $response = json_decode(json_encode($session), true) ?: [];
            if ($failureMessage) {
                $response['last_fulfillment_error'] = $failureMessage;
            }

            if ($existing !== null) {
                $existing->update([
                    'status' => 'failed',
                    'reference_number' => self::stripeCheckoutReferenceNumber($session) ?? $existing->reference_number,
                    'processor_status' => $session->payment_status ?? $existing->processor_status,
                    'processor_response' => $response,
                ]);

                return;
            }

            Payment::create([
                'invoice_id' => $invoice->id,
                'configuration_id' => PaymentConfiguration::forStripe()->id,
                'payment_method_code' => $this->resolveCheckoutPaymentMethodCode(PaymentConfiguration::forStripe(), $session),
                'status' => 'failed',
                'amount' => 0,
                'surcharge_amount' => 0,
                'net_amount' => 0,
                'currency' => strtoupper((string) ($invoice->currency ?: 'USD')),
                'processor' => 'stripe',
                'processor_transaction_id' => $sessionId,
                'reference_number' => self::stripeCheckoutReferenceNumber($session),
                'processor_status' => $session->payment_status ?? null,
                'processor_response' => $response,
                'paid_at' => null,
            ]);
        });
    }

    /**
     * Stripe Checkout: best stable payment reference for staff search / reconciliation.
     * Prefers Charge id when present, else PaymentIntent id.
     */
    private static function stripeCheckoutReferenceNumber(Session $session): ?string
    {
        $pi = $session->payment_intent ?? null;
        if (is_string($pi) && str_starts_with($pi, 'pi_')) {
            return $pi;
        }
        if (is_object($pi)) {
            $latestCharge = $pi->latest_charge ?? null;
            if (is_string($latestCharge) && str_starts_with($latestCharge, 'ch_')) {
                return $latestCharge;
            }
            if (is_object($latestCharge)) {
                $achRef = self::stripeAchPaymentReferenceFromCharge($latestCharge);
                if ($achRef !== null) {
                    return $achRef;
                }
                $chargeId = (string) ($latestCharge->id ?? '');
                if (str_starts_with($chargeId, 'ch_')) {
                    return $chargeId;
                }
            }
            $id = $pi->id ?? null;
            if (is_string($id) && str_starts_with($id, 'pi_')) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Stripe Dashboard “Payment reference” for US bank debits when present on the Charge.
     */
    private static function stripeAchPaymentReferenceFromCharge(object $charge): ?string
    {
        $details = $charge->payment_method_details ?? null;
        if (! is_object($details)) {
            return null;
        }
        $bank = $details->us_bank_account ?? null;
        if (! is_object($bank)) {
            return null;
        }
        $reference = $bank->reference ?? $bank->payment_reference ?? null;
        if (! is_string($reference) || trim($reference) === '') {
            return null;
        }

        return substr(trim($reference), 0, 255);
    }

    /**
     * Map Stripe Checkout / PaymentIntent data to {@see PaymentMethod} enum values stored on {@see Payment}.
     */
    private function resolveCheckoutPaymentMethodCode(PaymentConfiguration $configuration, Session $session): string
    {
        $pi = $session->payment_intent ?? null;
        if (! is_object($pi)) {
            return PaymentMethod::CreditCard->value;
        }

        $fromCharge = $this->resolvePaymentMethodCodeFromLatestCharge($configuration, $pi);
        if ($fromCharge !== null) {
            return $fromCharge;
        }

        $pm = $pi->payment_method ?? null;
        if (is_object($pm)) {
            return $this->mapStripePaymentMethodTypeToCode((string) ($pm->type ?? ''));
        }

        if (is_string($pm) && str_starts_with($pm, 'pm_')) {
            try {
                $pmObj = $this->stripe->retrievePaymentMethod($configuration, $pm);

                return $this->mapStripePaymentMethodTypeToCode((string) ($pmObj->type ?? ''));
            } catch (\Throwable) {
                // Fall through to PI payment_method_types hint.
            }
        }

        return $this->resolvePaymentMethodCodeFromIntentTypes($pi)
            ?? PaymentMethod::CreditCard->value;
    }

    private function resolvePaymentMethodCodeFromLatestCharge(PaymentConfiguration $configuration, object $pi): ?string
    {
        $latestCharge = $pi->latest_charge ?? null;
        if (is_object($latestCharge)) {
            $details = $latestCharge->payment_method_details ?? null;
            $type = is_object($details) ? (string) ($details->type ?? '') : '';

            return $type !== '' ? $this->mapStripePaymentMethodTypeToCode($type) : null;
        }

        if (is_string($latestCharge) && str_starts_with($latestCharge, 'ch_')) {
            try {
                $charge = $this->stripe->retrieveCharge($configuration, $latestCharge);
                $details = $charge->payment_method_details ?? null;
                $type = is_object($details) ? (string) ($details->type ?? '') : '';

                return $type !== '' ? $this->mapStripePaymentMethodTypeToCode($type) : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function resolvePaymentMethodCodeFromIntentTypes(object $pi): ?string
    {
        $types = $pi->payment_method_types ?? null;
        if (! is_array($types) || $types === []) {
            return null;
        }

        if (count($types) === 1 && $types[0] === 'us_bank_account') {
            return PaymentMethod::Ach->value;
        }

        if (count($types) === 1 && $types[0] === 'card') {
            return PaymentMethod::CreditCard->value;
        }

        if (in_array('us_bank_account', $types, true) && ! in_array('card', $types, true)) {
            return PaymentMethod::Ach->value;
        }

        return null;
    }

    private function mapStripePaymentMethodTypeToCode(string $stripeType): string
    {
        return match ($stripeType) {
            'us_bank_account' => PaymentMethod::Ach->value,
            'card' => PaymentMethod::CreditCard->value,
            default => PaymentMethod::CreditCard->value,
        };
    }
}
