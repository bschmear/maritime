<?php

namespace App\Services\Payments;

use App\Domain\Payment\Models\PaymentConfiguration;
use App\Models\AccountSettings;

class PaymentService
{
    public function createCheckout(int $amountCents, string $type = 'full'): string
    {
        $settings = AccountSettings::getCurrent();
        $provider = $settings->payment_provider ?? 'stripe';

        if ($provider !== 'stripe') {
            throw new \RuntimeException('Unsupported payment provider.');
        }

        $configuration = PaymentConfiguration::forCurrentAccount($settings);

        return app(StripeService::class)->createCheckout($configuration, $amountCents, $type);
    }
}
