<?php

namespace App\Enums\Invoice;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

/**
 * Values must match the tenant {@code invoices.status} enum column.
 */
enum Status: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';

    public function id(): int
    {
        return match ($this) {
            self::Draft => 1,
            self::Sent => 2,
            self::Viewed => 3,
            self::Partial => 4,
            self::Paid => 5,
            self::Void => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Viewed => 'Viewed',
            self::Partial => 'Partially Paid',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'blue',
            self::Viewed => 'indigo',
            self::Partial => 'amber',
            self::Paid => 'green',
            self::Void => 'red',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

            self::Sent => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',

            self::Viewed => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',

            self::Partial => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',

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
