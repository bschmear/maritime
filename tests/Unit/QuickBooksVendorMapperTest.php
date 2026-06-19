<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\QuickBooks\QuickBooksVendorMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QuickBooksVendorMapperTest extends TestCase
{
    #[Test]
    public function map_vendor_row_includes_balance_1099_and_ach_bank_detail(): void
    {
        $row = [
            'Id' => '42',
            'SyncToken' => '3',
            'DisplayName' => 'Acme Supplies',
            'CompanyName' => 'Acme Supplies LLC',
            'Balance' => 1250.55,
            'Vendor1099' => true,
            'AcctNum' => 'V-100',
            'TaxIdentifier' => '12-3456789',
            'PrintOnCheckName' => 'Acme Supplies LLC',
            'Active' => true,
            'PrimaryEmailAddr' => ['Address' => 'ap@acme.test'],
            'PrimaryPhone' => ['FreeFormNumber' => '555-0100'],
            'VendorPaymentBankDetail' => [
                'BankAccountName' => 'Operating',
                'BankAccountNumber' => '9876543210',
                'BankBranchIdentifier' => '021000021',
            ],
            'TermRef' => ['value' => '3', 'name' => 'Net 30'],
            'BillAddr' => [
                'Line1' => '100 Main St',
                'City' => 'Anytown',
                'CountrySubDivisionCode' => 'CA',
                'PostalCode' => '90210',
                'Country' => 'USA',
            ],
        ];

        $payload = QuickBooksVendorMapper::mapVendorRow($row);

        $this->assertSame('Acme Supplies', $payload['display_name']);
        $this->assertSame('42', $payload['quickbooks_id']);
        $this->assertSame(1250.55, $payload['open_balance']);
        $this->assertTrue($payload['vendor_1099']);
        $this->assertSame('Operating', $payload['ach_bank_name']);
        $this->assertSame('9876543210', $payload['ach_account_number']);
        $this->assertSame('021000021', $payload['ach_routing_number']);
        $this->assertSame('12-3456789', $payload['tax_identifier']);
        $this->assertSame('3', $payload['term_ref_id']);
        $this->assertSame('Net 30', $payload['term_ref_name']);
    }

    #[Test]
    public function map_vendor_row_nulls_invalid_contact_email(): void
    {
        $payload = QuickBooksVendorMapper::mapVendorRow([
            'Id' => '99',
            'DisplayName' => 'Bad Email Vendor',
            'PrimaryEmailAddr' => ['Address' => 'not-an-email'],
        ]);

        $this->assertNull($payload['contact_email']);
    }

    #[Test]
    public function merge_read_row_fills_bank_detail_when_query_row_omits_it(): void
    {
        $merged = QuickBooksVendorMapper::mergeReadRow(
            ['Id' => '42', 'DisplayName' => 'Acme'],
            [
                'VendorPaymentBankDetail' => [
                    'BankAccountName' => 'Operating',
                    'BankAccountNumber' => '123456789',
                    'BankBranchIdentifier' => '021000021',
                ],
                'TaxIdentifier' => '12-3456789',
            ],
        );

        $payload = QuickBooksVendorMapper::mapVendorRow($merged);

        $this->assertSame('Operating', $payload['ach_bank_name']);
        $this->assertSame('123456789', $payload['ach_account_number']);
        $this->assertSame('021000021', $payload['ach_routing_number']);
        $this->assertSame('12-3456789', $payload['tax_identifier']);
    }

    #[Test]
    public function preserve_sensitive_fields_when_absent_does_not_blank_existing_values(): void
    {
        $payload = QuickBooksVendorMapper::preserveSensitiveFieldsWhenAbsent([
            'display_name' => 'Vendor',
            'ach_account_number' => '',
            'ach_routing_number' => null,
        ]);

        $this->assertArrayNotHasKey('ach_account_number', $payload);
        $this->assertArrayNotHasKey('ach_routing_number', $payload);
        $this->assertSame('Vendor', $payload['display_name']);
    }
}
