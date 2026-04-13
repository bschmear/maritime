<?php

namespace App\Domain\Invoice\Support;

use App\Domain\Payment\Models\PaymentConfiguration;
use Illuminate\Validation\Rule;

final class InvoicePaymentFields
{
    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        $codes = collect(PaymentConfiguration::enabledStripeMethodOptionsForCurrentAccount())
            ->pluck('code')
            ->all();

        return [
            'allowed_methods' => ['nullable', 'array'],
            'allowed_methods.*' => ['string', 'max:50', Rule::in($codes)],
            'surcharge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allow_partial_payment' => ['sometimes', 'boolean'],
            'minimum_partial_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validatedPayment  Subset of validator output for payment keys
     * @param  array<string, mixed>  $rawRequest
     * @return array<string, mixed>
     */
    public static function normalizeForPersistence(array $validatedPayment, array $rawRequest): array
    {
        $allowPartial = filter_var($rawRequest['allow_partial_payment'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $methods = $validatedPayment['allowed_methods'] ?? null;
        if (is_array($methods)) {
            $methods = array_values(array_unique(array_filter(
                $methods,
                fn ($c) => is_string($c) && $c !== ''
            )));
        } else {
            $methods = null;
        }

        $surcharge = $validatedPayment['surcharge_percent'] ?? null;
        if ($surcharge === '' || $surcharge === null) {
            $surcharge = null;
        } else {
            $surcharge = round((float) $surcharge, 2);
        }

        $minPartial = $validatedPayment['minimum_partial_amount'] ?? null;
        if (! $allowPartial) {
            $minPartial = null;
        } elseif ($minPartial === '' || $minPartial === null) {
            $minPartial = null;
        } else {
            $minPartial = round((float) $minPartial, 2);
        }

        return [
            'allowed_methods' => $methods,
            'surcharge_percent' => $surcharge,
            'allow_partial_payment' => $allowPartial,
            'minimum_partial_amount' => $minPartial,
        ];
    }
}
