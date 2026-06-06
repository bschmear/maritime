<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\MsoRecord\Support\MsoRecordDetails;
use App\Domain\MsoRecord\Support\MsoValueResolver;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MsoValueResolverTest extends TestCase
{
    public function test_prefill_map_reads_snapshot_customer_fields(): void
    {
        $record = new MsoRecord;
        $record->details = MsoRecordDetails::build([
            'transaction' => [
                'customer_name' => 'Jane Buyer',
                'customer_phone' => '555-0100',
                'customer_title' => 'Owner',
                'customer_address' => "123 Harbor Ln\nMiami, FL",
            ],
            'subsidiary' => [
                'display_name' => 'Atlantic Marine',
            ],
            'line_item' => [
                'name' => '2024 Sundancer',
                'description' => 'Twin engines',
                'unit_price' => 125000.5,
            ],
        ], null, []);

        $map = MsoValueResolver::prefillMap($record);

        $this->assertSame('Jane Buyer', $map['customer_name']);
        $this->assertSame('555-0100', $map['customer_phone']);
        $this->assertSame('Owner', $map['customer_title']);
        $this->assertStringContainsString('123 Harbor Ln', $map['customer_address']);
        $this->assertSame('Atlantic Marine', $map['dealership_name']);
        $this->assertStringContainsString('2024 Sundancer', $map['line_item']);
        $this->assertSame('$125,000.50', $map['line_item_price']);
    }

    public function test_date_time_prefill_uses_account_timezone(): void
    {
        $at = Carbon::parse('2026-06-03 14:30:00', 'UTC');

        $map = MsoValueResolver::dateTimePrefill($at, 'America/Chicago');

        $this->assertSame('06/03/2026', $map['date']);
        $this->assertSame('June', $map['current_month']);
        $this->assertSame('3', $map['current_day']);
        $this->assertSame('2026', $map['current_year']);
        $this->assertSame('9:30 AM', $map['current_time']);
    }

    public function test_format_location_address_supports_multiline_layout(): void
    {
        $formatted = MsoValueResolver::formatLocationAddress([
            'address_line_1' => '500 Marina Blvd',
            'address_line_2' => 'Dock 12',
            'city' => 'Fort Lauderdale',
            'state' => 'FL',
            'postal_code' => '33316',
            'country' => 'US',
        ], 'multiline');

        $this->assertStringContainsString("500 Marina Blvd\nDock 12", $formatted);
        $this->assertStringContainsString('Fort Lauderdale, FL, 33316', $formatted);
    }

    public function test_format_customer_address_supports_single_line_layout(): void
    {
        $formatted = MsoValueResolver::formatCustomerAddress([
            'billing_address_line1' => '123 Harbor Ln',
            'billing_address_line2' => 'Suite 5',
            'billing_city' => 'Miami',
            'billing_state' => 'FL',
            'billing_postal' => '33101',
            'billing_country' => 'US',
        ], 'single');

        $this->assertSame('123 Harbor Ln, Suite 5, Miami, FL, 33101, US', $formatted);
    }

    public function test_format_customer_address_supports_multiline_layout(): void
    {
        $formatted = MsoValueResolver::formatCustomerAddress([
            'billing_address_line1' => '123 Harbor Ln',
            'billing_address_line2' => 'Suite 5',
            'billing_city' => 'Miami',
            'billing_state' => 'FL',
            'billing_postal' => '33101',
            'billing_country' => 'US',
        ], 'multiline');

        $this->assertStringContainsString("123 Harbor Ln\nSuite 5", $formatted);
        $this->assertStringContainsString('Miami, FL, 33101', $formatted);
    }

    public function test_mso_record_details_normalizes_legacy_snapshot_shape(): void
    {
        $normalized = MsoRecordDetails::normalize([
            'transaction' => ['customer_name' => 'Legacy'],
        ]);

        $this->assertArrayHasKey('snapshot', $normalized);
        $this->assertSame('Legacy', $normalized['snapshot']['transaction']['customer_name']);
        $this->assertSame([], $normalized['fields']);
    }
}
