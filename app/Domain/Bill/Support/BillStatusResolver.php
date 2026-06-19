<?php

declare(strict_types=1);

namespace App\Domain\Bill\Support;

use App\Enums\Bill\Status;
use Carbon\CarbonInterface;

final class BillStatusResolver
{
    public static function resolve(float $balance, ?CarbonInterface $dueDate, bool $void = false): Status
    {
        if ($void) {
            return Status::Void;
        }

        if (round($balance, 2) <= 0) {
            return Status::Paid;
        }

        if ($dueDate !== null && $dueDate->copy()->startOfDay()->lt(now()->startOfDay())) {
            return Status::Overdue;
        }

        return Status::Open;
    }

    public static function resolveValue(float $balance, ?CarbonInterface $dueDate, bool $void = false): string
    {
        return self::resolve($balance, $dueDate, $void)->value;
    }
}
