<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Bill\Status;
use App\Support\Enum\StoredEnumNormalizer;
use App\Support\QuickBooks\QuickBooksBillMapper;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BillStatusMappingTest extends TestCase
{
    #[Test]
    public function stored_enum_normalizer_maps_numeric_ids_to_string_values(): void
    {
        $this->assertSame('open', StoredEnumNormalizer::normalizeForEnum(1, Status::class));
        $this->assertSame('overdue', StoredEnumNormalizer::normalizeForEnum('2', Status::class));
        $this->assertSame('paid', StoredEnumNormalizer::normalizeForEnum('paid', Status::class));
    }

    #[Test]
    public function stored_enum_normalizer_maps_filter_arrays(): void
    {
        $fieldsSchema = [
            'status' => ['enum' => Status::class],
        ];

        $normalized = StoredEnumNormalizer::normalizeForField([1, 'overdue'], 'status', $fieldsSchema);

        $this->assertSame(['open', 'overdue'], $normalized);
    }

    #[Test]
    public function quickbooks_bill_mapper_derives_status_from_balance_and_due_date(): void
    {
        $method = new \ReflectionMethod(QuickBooksBillMapper::class, 'resolveStatusFromQuickBooks');
        $method->setAccessible(true);

        $status = $method->invoke(null, [
            'Id' => '10',
            'Balance' => 125.5,
            'DueDate' => '2020-01-01',
        ], 125.5, Carbon::parse('2020-01-01'));

        $this->assertSame('overdue', $status);
    }

    #[Test]
    public function quickbooks_bill_mapper_normalizes_raw_status_when_present(): void
    {
        $method = new \ReflectionMethod(QuickBooksBillMapper::class, 'resolveStatusFromQuickBooks');
        $method->setAccessible(true);

        $status = $method->invoke(null, [
            'Id' => '11',
            'Balance' => 0,
            'Status' => 3,
        ], 0.0, null);

        $this->assertSame('paid', $status);
    }
}
