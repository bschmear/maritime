<?php

namespace App\Services\Payments;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Models\PaymentConfiguration;
use App\Models\AccountSettings;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripeService
{
    public function __construct()
    {
        $secret = config('cashier.secret') ?: config('services.stripe.secret');
        Stripe::setApiKey($secret);
    }

    /**
     * Create Stripe Connect Express account (charges go to connected account).
     *
     * Requesting {@code card_payments} and {@code transfers} is required or Checkout/charges
     * on the connected account fail with “card_payments capability” errors until Stripe
     * activates them after onboarding.
     */
    public function createConnectedAccount(): Account
    {
        return Account::create([
            'type' => 'express',
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);
    }

    /**
     * For accounts created before capabilities were requested, ask Stripe to enable them.
     * Safe to call repeatedly for “Continue setup” flows.
     */
    public function ensureRequestedCapabilities(string $accountId): void
    {
        Account::update($accountId, [
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);
    }

    public function createOnboardingLink(string $accountId): string
    {
        $link = AccountLink::create([
            'account' => $accountId,
            'refresh_url' => route('stripe.refresh'),
            'return_url' => route('stripe.return'),
            'type' => 'account_onboarding',
        ]);

        return $link->url;
    }

    public function createCheckout(PaymentConfiguration $configuration, int $amountCents, string $type): string
    {
        if (! $configuration->stripeReadyForCharges()) {
            throw new \RuntimeException('Stripe account is not ready to accept payments.');
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => ucfirst($type).' Payment',
                    ],
                    'unit_amount' => $amountCents,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('payments.success'),
            'cancel_url' => route('payments.cancel'),
        ], [
            'stripe_account' => $configuration->stripe_account_id,
        ]);

        return $session->url;
    }

    /**
     * One-time Checkout for a tenant invoice (Stripe Connect destination charges on the connected account).
     *
     * @param  array{principal: string, surcharge: string}  $metadataAmounts  Decimal strings for fulfillment verification
     */
    public function createInvoiceCheckoutSession(
        PaymentConfiguration $configuration,
        Invoice $invoice,
        int $amountTotalCents,
        array $metadataAmounts,
        string $successUrl,
        string $cancelUrl,
    ): string {
        if (! $configuration->stripeReadyForCharges()) {
            throw new \RuntimeException('Stripe account is not ready to accept payments.');
        }

        $currency = strtolower((string) ($invoice->currency ?: 'usd'));
        if (strlen($currency) !== 3) {
            $currency = 'usd';
        }

        $business = AccountSettings::getCurrent()->business_name ?? 'Merchant';

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => 'Invoice '.$invoice->display_name,
                        'description' => 'Payment to '.$business,
                    ],
                    'unit_amount' => $amountTotalCents,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $invoice->uuid,
            'metadata' => [
                'invoice_id' => (string) $invoice->id,
                'invoice_uuid' => (string) $invoice->uuid,
                'principal' => $metadataAmounts['principal'],
                'surcharge' => $metadataAmounts['surcharge'],
            ],
        ], [
            'stripe_account' => $configuration->stripe_account_id,
        ]);

        return $session->url;
    }

    public function retrieveCheckoutSession(PaymentConfiguration $configuration, string $sessionId): Session
    {
        return Session::retrieve($sessionId, [
            'stripe_account' => $configuration->stripe_account_id,
        ]);
    }

    public function syncAccount(PaymentConfiguration $configuration): void
    {
        if (! $configuration->stripe_account_id) {
            return;
        }

        $account = Account::retrieve($configuration->stripe_account_id);
        $this->applyStripeAccountObjectToConfiguration($configuration, $account);
    }

    /**
     * Merge Stripe account fields (e.g. from {@see Account::retrieve} or webhook JSON) into config.
     *
     * @param  array<string, mixed>  $accountPayload  Stripe Account object as array (e.g. webhook {@code data.object})
     */
    public function applyAccountPayloadToConfiguration(PaymentConfiguration $configuration, array $accountPayload): void
    {
        $prev = $configuration->meta ?? [];
        $detailsSubmitted = (bool) ($accountPayload['details_submitted'] ?? false);
        $rawCaps = $accountPayload['capabilities'] ?? null;
        $capabilities = [];
        if (is_array($rawCaps)) {
            $capabilities = $rawCaps;
        } elseif (is_object($rawCaps)) {
            $capabilities = json_decode(json_encode($rawCaps), true) ?: [];
        }

        $configuration->update([
            'stripe_charges_enabled' => (bool) ($accountPayload['charges_enabled'] ?? false),
            'stripe_payouts_enabled' => (bool) ($accountPayload['payouts_enabled'] ?? false),
            'meta' => array_merge($prev, [
                'details_submitted' => $detailsSubmitted,
                'email' => $accountPayload['email'] ?? $prev['email'] ?? null,
                'connected_at' => $prev['connected_at'] ?? ($detailsSubmitted ? now()->toIso8601String() : null),
                'stripe_capability_card_payments' => $capabilities['card_payments'] ?? $prev['stripe_capability_card_payments'] ?? null,
                'stripe_capability_transfers' => $capabilities['transfers'] ?? $prev['stripe_capability_transfers'] ?? null,
            ]),
        ]);
    }

    private function applyStripeAccountObjectToConfiguration(PaymentConfiguration $configuration, Account $account): void
    {
        $this->applyAccountPayloadToConfiguration($configuration, $account->toArray());
    }
}
