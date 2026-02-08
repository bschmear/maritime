<?php

namespace App\Enums\WorkOrder;

enum Status: string
{
    case Draft       = 'draft';
    case Open        = 'open';
    case Scheduled   = 'scheduled';
    case InProgress  = 'in_progress';
    case Waiting     = 'waiting';
    case Blocked     = 'blocked';
    case Completed   = 'completed';
    case Closed      = 'closed';
    case Cancelled   = 'cancelled';

    public function id(): int
    {
        return match ($this) {
            self::Draft       => 1,
            self::Open        => 2,
            self::Scheduled   => 3,
            self::InProgress  => 4,
            self::Waiting     => 5,
            self::Blocked     => 6,
            self::Completed   => 7,
            self::Closed      => 8,
            self::Cancelled   => 9,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft       => 'Draft',
            self::Open        => 'Open',
            self::Scheduled   => 'Scheduled',
            self::InProgress  => 'In Progress',
            self::Waiting     => 'Waiting',
            self::Blocked     => 'Blocked',
            self::Completed   => 'Completed',
            self::Closed      => 'Closed',
            self::Cancelled   => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft       => 'gray',
            self::Open        => 'blue',
            self::Scheduled   => 'indigo',
            self::InProgress  => 'yellow',
            self::Waiting     => 'gray',
            self::Blocked     => 'red',
            self::Completed   => 'green',
            self::Closed      => 'slate',
            self::Cancelled   => 'red',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Draft       => 'bg-gray-200 dark:bg-gray-900 dark:text-white',
            self::Open        => 'bg-blue-200 dark:bg-blue-900 dark:text-white',
            self::Scheduled   => 'bg-indigo-200 dark:bg-indigo-900 dark:text-white',
            self::InProgress  => 'bg-yellow-200 dark:bg-yellow-900 dark:text-white',
            self::Waiting     => 'bg-gray-200 dark:bg-gray-900 dark:text-white',
            self::Blocked     => 'bg-red-200 dark:bg-red-900 dark:text-white',
            self::Completed   => 'bg-green-200 dark:bg-green-900 dark:text-white',
            self::Closed      => 'bg-slate-200 dark:bg-slate-900 dark:text-white',
            self::Cancelled   => 'bg-red-200 dark:bg-red-900 dark:text-white',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn(self $case) => [
                'id'      => $case->id(),
                'value'   => $case->value,
                'name'    => $case->label(),
                'color'   => $case->color(),
                'bgClass' => $case->bgClass(),
            ],
            self::cases()
        );
    }
}
