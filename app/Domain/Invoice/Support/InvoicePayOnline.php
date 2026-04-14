<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Support;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Models\PaymentConfiguration;

final class InvoicePayOnline
{
    public static function canPayOnline(Invoice $invoice): bool
    {
        if (in_array($invoice->status, ['void', 'paid', 'draft'], true)) {
            return false;
        }
        if ((float) $invoice->amount_due <= 0) {
            return false;
        }

        $config = PaymentConfiguration::forStripe();

        return $config->stripeReadyForCharges() && self::invoiceAcceptsCardOnline($invoice);
    }

    private static function invoiceAcceptsCardOnline(Invoice $invoice): bool
    {
        $enabled = collect(PaymentConfiguration::enabledStripeMethodOptionsForCurrentAccount())
            ->pluck('code')
            ->all();
        $onInvoice = $invoice->allowed_methods;
        $allowed = $onInvoice === null
            ? $enabled
            : array_values(array_intersect($onInvoice, $enabled));

        return in_array('credit_card', $allowed, true);
    }
}
