<?php

namespace App\Services\Payments;

use App\Models\PaymentAccount;

class PaymentService
{
    public function createCheckout($tenant, int $amount, string $type = 'full')
    {
        $settings = $tenant->accountSettings;

        $provider = $settings->payment_provider;

        $paymentAccount = PaymentAccount::where('account_settings_id', $settings->id)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->firstOrFail();

        return match ($provider) {
            'stripe' => app(StripeService::class)
                ->createCheckout($paymentAccount, $amount, $type),
            default => throw new \Exception('Unsupported payment provider'),
        };
    }
}
