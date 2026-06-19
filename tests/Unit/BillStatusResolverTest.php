<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Bill\Support\BillStatusResolver;
use App\Enums\Bill\Status;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BillStatusResolverTest extends TestCase
{
    #[Test]
    public function resolves_paid_when_balance_is_zero(): void
    {
        $this->assertSame(Status::Paid, BillStatusResolver::resolve(0, Carbon::parse('2020-01-01')));
    }

    #[Test]
    public function resolves_overdue_when_balance_positive_and_past_due(): void
    {
        $this->assertSame(
            Status::Overdue,
            BillStatusResolver::resolve(50, Carbon::yesterday()),
        );
    }

    #[Test]
    public function resolves_open_when_balance_positive_and_not_due(): void
    {
        $this->assertSame(
            Status::Open,
            BillStatusResolver::resolve(50, Carbon::tomorrow()),
        );
    }

    #[Test]
    public function resolves_void_when_flagged(): void
    {
        $this->assertSame(
            Status::Void,
            BillStatusResolver::resolve(100, Carbon::yesterday(), true),
        );
    }
}
