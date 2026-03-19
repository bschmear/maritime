<?php

namespace App\Services\Payments;

use App\Models\PaymentAccount;
use Stripe\Stripe;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Checkout\Session;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create Stripe Connect Account
     */
    public function createConnectedAccount(): Account
    {
        return Account::create([
            'type' => 'express',
        ]);
    }

    /**
     * Generate onboarding link
     */
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

    /**
     * Create Checkout Session
     */
    public function createCheckout(PaymentAccount $account, int $amount, string $type)
    {
        if (!$account->charges_enabled) {
            throw new \Exception('Stripe account is not ready to accept payments.');
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => ucfirst($type) . ' Payment',
                    ],
                    'unit_amount' => $amount, // already in cents
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('payments.success'),
            'cancel_url' => route('payments.cancel'),
        ], [
            'stripe_account' => $account->external_account_id,
        ]);

        return $session->url;
    }

    /**
     * Sync account status from Stripe
     */
    public function syncAccount(PaymentAccount $paymentAccount)
    {
        $account = Account::retrieve($paymentAccount->external_account_id);

        $paymentAccount->update([
            'charges_enabled' => $account->charges_enabled,
            'payouts_enabled' => $account->payouts_enabled,
            'data' => array_merge($paymentAccount->data ?? [], [
                'details_submitted' => $account->details_submitted,
                'email' => $account->email,
            ]),
            'connected_at' => now(),
        ]);
    }
}
