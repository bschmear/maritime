<?php

namespace App\Enums\Contract;

enum ContractPaymentStatus: string
{
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Refunded = 'refunded';

    public function id(): int
    {
        return match ($this) {
            self::Pending => 1,
            self::PartiallyPaid => 2,
            self::Paid => 3,
            self::Overdue => 4,
            self::Refunded => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Refunded => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::PartiallyPaid => 'orange',
            self::Paid => 'green',
            self::Overdue => 'red',
            self::Refunded => 'gray',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
            self::PartiallyPaid => 'bg-orange-200 dark:text-white dark:bg-orange-900',
            self::Paid => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Overdue => 'bg-red-200 dark:text-white dark:bg-red-900',
            self::Refunded => 'bg-gray-200 dark:text-white dark:bg-gray-900',
        };
    }

    public static function options(): array
    {
        return array_map(fn(self $case) => [
            'id'      => $case->id(),
            'value'   => $case->value,
            'name'    => $case->label(),
            'color'   => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }
}