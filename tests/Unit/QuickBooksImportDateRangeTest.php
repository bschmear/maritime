<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\QuickBooks\QuickBooksImportDateRange;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickBooksImportDateRangeTest extends TestCase
{
    #[Test]
    public function validate_accepts_a_one_year_range(): void
    {
        $result = QuickBooksImportDateRange::validate([
            'txn_date_from' => '2025-01-01',
            'txn_date_to' => '2025-12-31',
        ]);

        $this->assertSame('2025-01-01', $result['txn_date_from']);
        $this->assertSame('2025-12-31', $result['txn_date_to']);
    }

    #[Test]
    public function validate_rejects_ranges_longer_than_one_year(): void
    {
        $this->expectException(ValidationException::class);

        QuickBooksImportDateRange::validate([
            'txn_date_from' => '2024-01-01',
            'txn_date_to' => '2025-01-02',
        ]);
    }

    #[Test]
    public function bill_query_includes_txn_date_filters(): void
    {
        $sql = QuickBooksImportDateRange::billQuery('2025-06-01', '2025-06-30', 1, 100);

        $this->assertSame(
            "select * from Bill where TxnDate >= '2025-06-01' and TxnDate <= '2025-06-30' STARTPOSITION 1 MAXRESULTS 100",
            $sql,
        );
    }

    #[Test]
    public function bill_payment_query_includes_txn_date_filters(): void
    {
        $sql = QuickBooksImportDateRange::billPaymentQuery('2025-06-01', '2025-06-30', 101, 100);

        $this->assertSame(
            "select * from BillPayment where TxnDate >= '2025-06-01' and TxnDate <= '2025-06-30' STARTPOSITION 101 MAXRESULTS 100",
            $sql,
        );
    }

    #[Test]
    public function validate_allows_exactly_one_year_on_the_end_date(): void
    {
        Carbon::setTestNow('2026-06-19');

        $result = QuickBooksImportDateRange::validate([
            'txn_date_from' => '2025-06-19',
            'txn_date_to' => '2026-06-19',
        ]);

        $this->assertSame('2026-06-19', $result['txn_date_to']);

        Carbon::setTestNow();
    }
}
