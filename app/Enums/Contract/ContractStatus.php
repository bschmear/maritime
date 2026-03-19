<?php

namespace App\Enums\Contract;

enum ContractStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Signed = 'signed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function id(): int
    {
        return match ($this) {
            self::Draft => 1,
            self::PendingApproval => 2,
            self::Signed => 3,
            self::Cancelled => 4,
            self::Expired => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Signed => 'Signed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'yellow',
            self::Signed => 'green',
            self::Cancelled => 'red',
            self::Expired => 'orange',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::PendingApproval => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
            self::Signed => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Cancelled => 'bg-red-200 dark:text-white dark:bg-red-900',
            self::Expired => 'bg-orange-200 dark:text-white dark:bg-orange-900',
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