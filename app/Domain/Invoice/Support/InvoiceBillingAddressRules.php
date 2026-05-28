<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Support;

final class InvoiceBillingAddressRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'billing_address_line1' => ['required', 'string', 'max:255'],
            'billing_city' => ['required', 'string', 'max:255'],
            'billing_state' => ['required', 'string', 'max:255'],
            'billing_postal' => ['required', 'string', 'max:50'],
            'billing_address_line2' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'billing_address_line1.required' => 'Billing street address is required.',
            'billing_city.required' => 'Billing city is required.',
            'billing_state.required' => 'Billing state is required.',
            'billing_postal.required' => 'Billing postal code is required.',
        ];
    }
}
