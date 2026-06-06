<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\MsoRecord\Support\MsoRecordDetails;
use App\Domain\MsoRecord\Support\MsoValueResolver;
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
            ],
        ], null, []);

        $map = MsoValueResolver::prefillMap($record);

        $this->assertSame('Jane Buyer', $map['customer_name']);
        $this->assertSame('555-0100', $map['customer_phone']);
        $this->assertSame('Owner', $map['customer_title']);
        $this->assertStringContainsString('123 Harbor Ln', $map['customer_address']);
        $this->assertSame('Atlantic Marine', $map['dealership_name']);
        $this->assertStringContainsString('2024 Sundancer', $map['line_item']);
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
