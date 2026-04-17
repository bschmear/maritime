<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Domain\Invoice\Actions\FulfillPublicInvoiceCheckoutSession;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentConfiguration;
use App\Models\PaymentAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

/**
 * Stripe Connect webhook processing (expects tenant DB context to be initialized).
 */
class StripeConnectWebhookHandler
{
    public function __construct(
        private FulfillPublicInvoiceCheckoutSession $fulfill,
    ) {}

    /**
     * Decode and verify the raw webhook body. When {@code STRIPE_WEBHOOK_SECRET} is unset,
     * JSON is parsed without verification (local dev only).
     *
     * @return array<string, mixed>
     *
     * @throws \Throwable when signature verification fails and a secret is configured
     */
    public static function decodePayloadFromRequest(Request $request): array
    {
        $payloadRaw = $request->getContent();
        $secret = (string) config('cashier.webhook.secret');
        if ($secret !== '') {
            $event = Webhook::constructEvent(
                $payloadRaw,
                $request->header('Stripe-Signature', ''),
                $secret,
            );

            return json_decode(json_encode($event), true) ?: [];
        }

        return json_decode($payloadRaw, true) ?: [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, StripeService $stripeService): void
    {
        $type = $payload['type'] ?? null;
        $connectedAccountId = $payload['account'] ?? null;
        $object = $payload['data']['object'] ?? [];

        switch ($type) {
            case 'account.updated':
                $this->handleAccountUpdated($stripeService, is_array($object) ? $object : []);

                break;

            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $this->handleCheckoutSucceeded($stripeService, $connectedAccountId, is_array($object) ? $object : []);

                break;

            case 'checkout.session.async_payment_failed':
            case 'checkout.session.expired':
                $this->handleCheckoutFailed($stripeService, $connectedAccountId, is_array($object) ? $object : []);

                break;
        }
    }

    /**
     * @param  array<string, mixed>  $obj
     */
    private function handleAccountUpdated(StripeService $stripeService, array $obj): void
    {
        $accountId = $obj['id'] ?? null;
        if (! $accountId) {
            return;
        }

        $config = PaymentConfiguration::query()
            ->where('stripe_account_id', $accountId)
            ->first();

        if ($config) {
            $stripeService->applyAccountPayloadToConfiguration($config, $obj);
        }

        PaymentAccount::query()
            ->where('external_account_id', $accountId)
            ->update([
                'charges_enabled' => (bool) ($obj['charges_enabled'] ?? false),
                'payouts_enabled' => (bool) ($obj['payouts_enabled'] ?? false),
            ]);
    }

    /**
     * @param  array<string, mixed>  $sessionObj
     */
    private function handleCheckoutSucceeded(
        StripeService $stripeService,
        ?string $connectedAccountId,
        array $sessionObj,
    ): void {
        $sessionId = $sessionObj['id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $invoice = $this->resolveInvoiceFromSessionObject($sessionObj);
        if ($invoice === null) {
            Log::info('Stripe webhook: checkout session has no matching invoice', [
                'session_id' => $sessionId,
            ]);

            return;
        }

        ($this->fulfill)($invoice, $sessionId);
    }

    /**
     * @param  array<string, mixed>  $sessionObj
     */
    private function handleCheckoutFailed(
        StripeService $stripeService,
        ?string $connectedAccountId,
        array $sessionObj,
    ): void {
        $sessionId = $sessionObj['id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $invoice = $this->resolveInvoiceFromSessionObject($sessionObj);
        if ($invoice === null) {
            return;
        }

        try {
            $session = $stripeService->retrieveCheckoutSessionForAccount(
                $connectedAccountId,
                $sessionId,
                ['payment_intent.payment_method', 'payment_intent.latest_charge'],
            );
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook: could not retrieve failed session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        DB::transaction(function () use ($invoice, $session, $sessionId): void {
            $existing = Payment::query()
                ->where('processor_transaction_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && in_array($existing->status, ['completed', 'refunded', 'partially_refunded'], true)) {
                return;
            }

            $this->fulfill->markSessionFailed($invoice, $session, $sessionId);
        });
    }

    /**
     * @param  array<string, mixed>  $sessionObj
     */
    private function resolveInvoiceFromSessionObject(array $sessionObj): ?Invoice
    {
        $meta = $sessionObj['metadata'] ?? [];
        if (! is_array($meta)) {
            $meta = [];
        }
        $invoiceId = $meta['invoice_id'] ?? null;
        $invoiceUuid = $meta['invoice_uuid'] ?? ($sessionObj['client_reference_id'] ?? null);

        $query = Invoice::query();
        if ($invoiceId) {
            return $query->where('id', (int) $invoiceId)->first();
        }
        if ($invoiceUuid) {
            return $query->where('uuid', $invoiceUuid)->first();
        }

        return null;
    }
}
