<?php

namespace Tests\Unit;

use App\Domain\Invoice\Support\InvoiceBillingAddressRules;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceBillingAddressRulesTest extends TestCase
{
    #[Test]
    public function billing_address_fields_are_required(): void
    {
        $validator = Validator::make([], InvoiceBillingAddressRules::rules(), InvoiceBillingAddressRules::messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('billing_address_line1', $validator->errors()->toArray());
        $this->assertArrayHasKey('billing_city', $validator->errors()->toArray());
        $this->assertArrayHasKey('billing_state', $validator->errors()->toArray());
        $this->assertArrayHasKey('billing_postal', $validator->errors()->toArray());
    }

    #[Test]
    public function valid_billing_address_passes(): void
    {
        $validator = Validator::make([
            'billing_address_line1' => '123 Main St',
            'billing_city' => 'Austin',
            'billing_state' => 'TX',
            'billing_postal' => '78701',
        ], InvoiceBillingAddressRules::rules(), InvoiceBillingAddressRules::messages());

        $this->assertFalse($validator->fails());
    }
}
