<?php

namespace App\Enums\Contacts;

enum PreferredContactTime: string
{
    case Morning   = 'morning';
    case Afternoon = 'afternoon';
    case Evening   = 'evening';

    public function id(): int
    {
        return match ($this) {
            self::Morning   => 1,
            self::Afternoon => 2,
            self::Evening   => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Morning   => 'Morning',
            self::Afternoon => 'Afternoon',
            self::Evening   => 'Evening',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Morning   => 'yellow',
            self::Afternoon => 'orange',
            self::Evening   => 'purple',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Morning   => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
            self::Afternoon => 'bg-orange-200 dark:text-white dark:bg-orange-900',
            self::Evening   => 'bg-purple-200 dark:text-white dark:bg-purple-900',
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
