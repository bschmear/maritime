<?php

namespace App\Enums\Estimate;

enum EstimateStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function id(): int
    {
        return match ($this) {
            self::Draft => 1,
            self::PendingApproval => 2,
            self::Approved => 3,
            self::Declined => 4,
            self::Expired => 5,
            self::Cancelled => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'yellow',
            self::Approved => 'green',
            self::Declined => 'red',
            self::Expired => 'orange',
            self::Cancelled => 'slate',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::PendingApproval => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
            self::Approved => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Declined => 'bg-red-200 dark:text-white dark:bg-red-900',
            self::Expired => 'bg-orange-200 dark:text-white dark:bg-orange-900',
            self::Cancelled => 'bg-slate-200 dark:text-white dark:bg-slate-900',
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
