<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Integration\Support\QuickBooksCustomerAddressMapper;
use PHPUnit\Framework\TestCase;

class QuickBooksCustomerAddressMapperTest extends TestCase
{
    public function test_maps_billing_and_shipping_addresses(): void
    {
        $addresses = QuickBooksCustomerAddressMapper::addressesFromCustomerRow([
            'BillAddr' => [
                'Line1' => '123 Main St',
                'Line2' => 'Suite 5',
                'City' => 'Austin',
                'CountrySubDivisionCode' => 'TX',
                'PostalCode' => '78701',
                'Country' => 'USA',
            ],
            'ShipAddr' => [
                'Line1' => '500 Dock Rd',
                'City' => 'Corpus Christi',
                'CountrySubDivisionCode' => 'TX',
                'PostalCode' => '78401',
                'Country' => 'USA',
            ],
        ]);

        $this->assertCount(2, $addresses);
        $this->assertSame('Billing', $addresses[0]['label']);
        $this->assertTrue($addresses[0]['is_primary']);
        $this->assertSame('123 Main St', $addresses[0]['address_line_1']);
        $this->assertSame('Shipping', $addresses[1]['label']);
        $this->assertFalse($addresses[1]['is_primary']);
        $this->assertSame('500 Dock Rd', $addresses[1]['address_line_1']);
    }

    public function test_creates_both_labels_when_billing_and_shipping_match(): void
    {
        $addr = [
            'Line1' => '123 Main St',
            'City' => 'Austin',
            'CountrySubDivisionCode' => 'TX',
            'PostalCode' => '78701',
        ];

        $addresses = QuickBooksCustomerAddressMapper::addressesFromCustomerRow([
            'BillAddr' => $addr,
            'ShipAddr' => $addr,
        ]);

        $this->assertCount(2, $addresses);
        $this->assertSame('Billing', $addresses[0]['label']);
        $this->assertTrue($addresses[0]['is_primary']);
        $this->assertSame('Shipping', $addresses[1]['label']);
        $this->assertFalse($addresses[1]['is_primary']);
        $this->assertSame('123 Main St', $addresses[1]['address_line_1']);
    }

    public function test_shipping_only_becomes_primary(): void
    {
        $addresses = QuickBooksCustomerAddressMapper::addressesFromCustomerRow([
            'ShipAddr' => [
                'Line1' => '500 Dock Rd',
                'City' => 'Corpus Christi',
            ],
        ]);

        $this->assertCount(1, $addresses);
        $this->assertSame('Shipping', $addresses[0]['label']);
        $this->assertTrue($addresses[0]['is_primary']);
    }
}
