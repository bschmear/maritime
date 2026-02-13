<?php

namespace App\Enums\ServiceTicket;

enum Status: string
{
    case Draft       = 'draft';
    case Open        = 'open';
    case Estimating  = 'estimating';
    case Approved    = 'approved';
    case InProgress  = 'in_progress';
    case Completed   = 'completed';
    case Closed      = 'closed';
    case Cancelled   = 'cancelled';

    public function id(): int
    {
        return match ($this) {
            self::Draft       => 1,
            self::Open        => 2,
            self::Estimating  => 3,
            self::Approved    => 4,
            self::InProgress  => 5,
            self::Completed   => 6,
            self::Closed      => 7,
            self::Cancelled   => 8,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft       => 'Draft',
            self::Open        => 'Open',
            self::Estimating  => 'Estimating',
            self::Approved    => 'Approved',
            self::InProgress  => 'In Progress',
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
            self::Estimating  => 'orange',
            self::Approved    => 'green',
            self::InProgress  => 'yellow',
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
            self::Estimating  => 'bg-orange-200 dark:bg-orange-900 dark:text-white',
            self::Approved    => 'bg-green-200 dark:bg-green-900 dark:text-white',
            self::InProgress  => 'bg-yellow-200 dark:bg-yellow-900 dark:text-white',
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
