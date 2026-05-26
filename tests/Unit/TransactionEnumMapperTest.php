<?php

namespace Tests\Unit;

use App\Enums\Transaction\TransactionStatus;
use App\Support\TransactionEnumMapper;
use PHPUnit\Framework\TestCase;

class TransactionEnumMapperTest extends TestCase
{
    public function test_numeric_status_three_maps_to_completed_not_failed(): void
    {
        $this->assertSame(
            TransactionStatus::Completed->value,
            TransactionEnumMapper::statusToValue(3),
        );
        $this->assertSame(
            TransactionStatus::Completed->value,
            TransactionEnumMapper::statusToValue('3'),
        );
    }

    public function test_numeric_status_four_maps_to_failed(): void
    {
        $this->assertSame(
            TransactionStatus::Failed->value,
            TransactionEnumMapper::statusToValue(4),
        );
    }

    public function test_legacy_string_won_maps_to_completed(): void
    {
        $this->assertSame(
            TransactionStatus::Completed->value,
            TransactionEnumMapper::statusToValue('won'),
        );
    }
}
