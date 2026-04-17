<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Support;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Payment\Models\PaymentConfiguration;

final class InvoicePayOnline
{
    /** App codes that map to Stripe Checkout payment rails for public invoice pay. */
    private const STRIPE_CHECKOUT_METHOD_CODES = ['credit_card', 'ach', 'wire'];

    public static function canPayOnline(Invoice $invoice): bool
    {
        if (in_array($invoice->status, ['void', 'paid', 'draft'], true)) {
            return false;
        }
        if ((float) $invoice->amount_due <= 0) {
            return false;
        }

        $config = PaymentConfiguration::forStripe();

        return $config->stripeReadyForCharges() && self::invoiceAcceptsStripeOnline($invoice);
    }

    /**
     * Whether this invoice allows at least one Stripe Checkout method (card and/or US bank debit).
     */
    public static function invoiceAcceptsStripeOnline(Invoice $invoice): bool
    {
        return self::resolvedStripeCheckoutCodes($invoice) !== [];
    }

    /**
     * @return list<string> App payment method codes (credit_card, ach, wire) enabled on the account and allowed on the invoice.
     */
    public static function resolvedStripeCheckoutCodes(Invoice $invoice): array
    {
        $enabled = collect(PaymentConfiguration::enabledStripeMethodOptionsForCurrentAccount())
            ->pluck('code')
            ->all();
        $onInvoice = $invoice->allowed_methods;
        $base = $onInvoice === null
            ? $enabled
            : array_values(array_intersect($onInvoice, $enabled));

        return array_values(array_intersect($base, self::STRIPE_CHECKOUT_METHOD_CODES));
    }

    /**
     * Stripe Checkout `payment_method_types` for this invoice (card, us_bank_account, or both).
     * ACH and wire (bank transfer) both use `us_bank_account` on Stripe; USD only for bank debit.
     *
     * @return list<string>
     */
    public static function stripeCheckoutPaymentMethodTypes(Invoice $invoice): array
    {
        $codes = self::resolvedStripeCheckoutCodes($invoice);
        $types = [];
        if (in_array('credit_card', $codes, true)) {
            $types[] = 'card';
        }
        $currency = strtolower((string) ($invoice->currency ?? 'usd'));
        if ($currency === 'usd' && array_intersect(['ach', 'wire'], $codes) !== []) {
            $types[] = 'us_bank_account';
        }

        return array_values(array_unique($types)) ?: ['card'];
    }

    /**
     * @return array{card: bool, bank: bool, codes: list<string>}
     */
    public static function payOnlineUiFlags(Invoice $invoice): array
    {
        $codes = self::resolvedStripeCheckoutCodes($invoice);

        return [
            'card' => in_array('credit_card', $codes, true),
            'bank' => array_intersect(['ach', 'wire'], $codes) !== [],
            'codes' => $codes,
        ];
    }
}
