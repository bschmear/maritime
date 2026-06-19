<?php

declare(strict_types=1);

namespace App\Enums\Bill;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

/**
 * Values must match the tenant {@code bills.status} enum column.
 */
enum Status: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Open = 'open';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case Void = 'void';

    public function id(): int
    {
        return match ($this) {
            self::Open => 1,
            self::Overdue => 2,
            self::Paid => 3,
            self::Void => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Overdue => 'Overdue',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Overdue => 'amber',
            self::Paid => 'green',
            self::Void => 'red',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            self::Overdue => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            self::Paid => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
            self::Void => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
            'color' => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }
}
