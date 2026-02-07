<?php

namespace App\Enums\WorkOrder;

enum Priority: string
{
    case Low      = 'low';
    case Normal   = 'normal';
    case High     = 'high';
    case Urgent   = 'urgent';

    public function id(): int
    {
        return match ($this) {
            self::Low    => 1,
            self::Normal => 2,
            self::High   => 3,
            self::Urgent => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low    => 'Low',
            self::Normal => 'Normal',
            self::High   => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low    => 'gray',
            self::Normal => 'blue',
            self::High   => 'orange',
            self::Urgent => 'red',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Low    => 'bg-gray-200 dark:bg-gray-900 dark:text-white',
            self::Normal => 'bg-blue-200 dark:bg-blue-900 dark:text-white',
            self::High   => 'bg-orange-200 dark:bg-orange-900 dark:text-white',
            self::Urgent => 'bg-red-200 dark:bg-red-900 dark:text-white',
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
