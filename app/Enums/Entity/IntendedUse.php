<?php

namespace App\Enums\Entity;

enum IntendedUse: string
{
    case Tender     = 'tender';
    case Cruising   = 'cruising';
    case Fishing    = 'fishing';
    case Recreation = 'recreation';
    case Commercial = 'commercial';
    case Other      = 'other';

    public function id(): int
    {
        return match ($this) {
            self::Tender     => 1,
            self::Cruising   => 2,
            self::Fishing    => 3,
            self::Recreation => 4,
            self::Commercial => 5,
            self::Other      => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Tender     => 'Tender',
            self::Cruising   => 'Cruising',
            self::Fishing    => 'Fishing',
            self::Recreation => 'Recreation',
            self::Commercial => 'Commercial',
            self::Other      => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Tender     => 'blue',
            self::Cruising   => 'purple',
            self::Fishing    => 'green',
            self::Recreation => 'orange',
            self::Commercial => 'teal',
            self::Other      => 'gray',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Tender     => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Cruising   => 'bg-purple-200 dark:text-white dark:bg-purple-900',
            self::Fishing    => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Recreation => 'bg-orange-200 dark:text-white dark:bg-orange-900',
            self::Commercial => 'bg-teal-200 dark:text-white dark:bg-teal-900',
            self::Other      => 'bg-gray-200 dark:text-white dark:bg-gray-900',
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