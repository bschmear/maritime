<?php

namespace App\Enums\Leads;

enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function id(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'green',
            self::Medium => 'yellow',
            self::High => 'orange',
            self::Urgent => 'red',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Low => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Medium => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
            self::High => 'bg-orange-200 dark:text-white dark:bg-orange-900',
            self::Urgent => 'bg-red-200 dark:text-white dark:bg-red-900',
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
