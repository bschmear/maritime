<?php

namespace App\Enums\Tasks;

enum TaskStatus: string
{
    case Open        = 'open';
    case InProgress  = 'in_progress';
    case Waiting     = 'waiting';
    case Blocked     = 'blocked';
    case Completed   = 'completed';

    public function id(): int
    {
        return match ($this) {
            self::Open        => 1,
            self::InProgress  => 2,
            self::Waiting     => 3,
            self::Blocked     => 4,
            self::Completed   => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Open        => 'Open',
            self::InProgress  => 'In Progress',
            self::Waiting     => 'Waiting',
            self::Blocked     => 'Blocked',
            self::Completed   => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open        => 'blue',
            self::InProgress  => 'yellow',
            self::Waiting     => 'gray',
            self::Blocked     => 'red',
            self::Completed   => 'green',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Open        => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::InProgress  => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
            self::Waiting     => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::Blocked     => 'bg-red-200 dark:text-white dark:bg-red-900',
            self::Completed   => 'bg-green-200 dark:text-white dark:bg-green-900',
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
