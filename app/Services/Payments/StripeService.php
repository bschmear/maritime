<?php

namespace App\Services\Payments;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Models\PaymentConfiguration;
use App\Models\AccountSettings;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
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
     * Requesting `card_payments` and `transfers` is required; Checkout/charges on the
     * connected account fail until Stripe activates `card_payments` after onboarding.
     */
    public function createConnectedAccount(): Account
    {
        return Account::create([
            'type' => 'express',
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
                'us_bank_account_ach_payments' => ['requested' => true],
            ],
        ]);
    }

    /**
     * For accounts created before capabilities were requested, or to recover from partial setup.
     * Safe to call repeatedly for “Continue setup” / refresh_url flows.
     */
    public function ensureRequestedCapabilities(string $accountId): void
    {
        Account::update($accountId, [
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
                'us_bank_account_ach_payments' => ['requested' => true],
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
     * @param  list<string>  $paymentMethodTypes  Stripe types, e.g. `['card']`, `['us_bank_account']`, or both
     */
    public function createInvoiceCheckoutSession(
        PaymentConfiguration $configuration,
        Invoice $invoice,
        int $amountTotalCents,
        array $metadataAmounts,
        string $successUrl,
        string $cancelUrl,
        array $paymentMethodTypes = ['card'],
    ): string {
        if (! $configuration->stripeReadyForCharges()) {
            throw new \RuntimeException('Stripe account is not ready to accept payments.');
        }

        $currency = strtolower((string) ($invoice->currency ?: 'usd'));
        if (strlen($currency) !== 3) {
            $currency = 'usd';
        }

        $business = AccountSettings::getCurrent()->business_name ?? 'Merchant';

        $types = array_values(array_unique($paymentMethodTypes));
        if ($types === []) {
            $types = ['card'];
        }

        $params = [
            'payment_method_types' => $types,
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
        ];

        if (in_array('us_bank_account', $types, true)) {
            $params['payment_method_options'] = [
                'us_bank_account' => [
                    'financial_connections' => [
                        'permissions' => ['payment_method'],
                    ],
                ],
            ];
        }

        $session = Session::create($params, [
            'stripe_account' => $configuration->stripe_account_id,
        ]);

        return $session->url;
    }

    /**
     * @param  list<string>  $expand  e.g. `['payment_intent.payment_method']`
     */
    public function retrieveCheckoutSession(PaymentConfiguration $configuration, string $sessionId, array $expand = []): Session
    {
        return $this->retrieveCheckoutSessionForAccount($configuration->stripe_account_id, $sessionId, $expand);
    }

    /**
     * @param  list<string>  $expand
     */
    public function retrieveCheckoutSessionForAccount(?string $stripeAccountId, string $sessionId, array $expand = []): Session
    {
        $opts = [];
        if ($stripeAccountId) {
            $opts['stripe_account'] = $stripeAccountId;
        }
        if ($expand !== []) {
            $opts['expand'] = $expand;
        }

        return Session::retrieve($sessionId, $opts);
    }

    /**
     * @param  list<string>  $expand
     */
    public function retrievePaymentIntent(PaymentConfiguration $configuration, string $paymentIntentId, array $expand = []): PaymentIntent
    {
        $opts = ['stripe_account' => $configuration->stripe_account_id];
        if ($expand !== []) {
            $opts['expand'] = $expand;
        }

        return PaymentIntent::retrieve($paymentIntentId, $opts);
    }

    public function retrievePaymentMethod(PaymentConfiguration $configuration, string $paymentMethodId): PaymentMethod
    {
        return PaymentMethod::retrieve(
            $paymentMethodId,
            ['stripe_account' => $configuration->stripe_account_id],
        );
    }

    public function retrieveCharge(PaymentConfiguration $configuration, string $chargeId): Charge
    {
        return Charge::retrieve(
            $chargeId,
            ['stripe_account' => $configuration->stripe_account_id],
        );
    }

    public function syncAccount(PaymentConfiguration $configuration): void
    {
        if (! $configuration->stripe_account_id) {
            return;
        }

        try {
            $account = Account::retrieve($configuration->stripe_account_id, [
                'expand' => ['capabilities'],
            ]);
        } catch (\Throwable) {
            $account = Account::retrieve($configuration->stripe_account_id);
        }

        $this->applyStripeAccountObjectToConfiguration($configuration, $account);
    }

    /**
     * Merge Stripe account fields (e.g. from {@see Account::retrieve} or webhook JSON) into config.
     *
     * @param  array<string, mixed>  $accountPayload  Stripe Account object as array (e.g. webhook `data.object`)
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

        $cardCap = array_key_exists('card_payments', $capabilities)
            ? self::normalizeStripeCapabilityValue($capabilities['card_payments'])
            : null;
        $transferCap = array_key_exists('transfers', $capabilities)
            ? self::normalizeStripeCapabilityValue($capabilities['transfers'])
            : null;
        $achCap = array_key_exists('us_bank_account_ach_payments', $capabilities)
            ? self::normalizeStripeCapabilityValue($capabilities['us_bank_account_ach_payments'])
            : null;

        $configuration->update([
            'stripe_charges_enabled' => (bool) ($accountPayload['charges_enabled'] ?? false),
            'stripe_payouts_enabled' => (bool) ($accountPayload['payouts_enabled'] ?? false),
            'meta' => array_merge($prev, [
                'details_submitted' => $detailsSubmitted,
                'email' => $accountPayload['email'] ?? $prev['email'] ?? null,
                'connected_at' => $prev['connected_at'] ?? ($detailsSubmitted ? now()->toIso8601String() : null),
                'stripe_capability_card_payments' => $cardCap ?? ($prev['stripe_capability_card_payments'] ?? null),
                'stripe_capability_transfers' => $transferCap ?? ($prev['stripe_capability_transfers'] ?? null),
                'stripe_capability_us_bank_account_ach_payments' => $achCap ?? ($prev['stripe_capability_us_bank_account_ach_payments'] ?? null),
            ]),
        ]);
    }

    /**
     * Stripe returns each capability as a status string or as an object with a `status` field.
     */
    private static function normalizeStripeCapabilityValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            if (isset($value['status']) && is_string($value['status'])) {
                return $value['status'];
            }

            return null;
        }
        if (is_object($value)) {
            return self::normalizeStripeCapabilityValue(json_decode(json_encode($value), true));
        }

        return null;
    }

    private function applyStripeAccountObjectToConfiguration(PaymentConfiguration $configuration, Account $account): void
    {
        $this->applyAccountPayloadToConfiguration($configuration, $account->toArray());
    }
}
