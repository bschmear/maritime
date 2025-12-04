<?php

namespace App\Enums\Entity;

enum Status: string
{
    case Lead       = 'lead';
    case Prospect   = 'prospect';
    case Customer   = 'customer';
    case Inactive   = 'inactive';
    case VIP        = 'vip';

    public function id(): int
    {
        return match ($this) {
            self::Lead      => 1,
            self::Prospect  => 2,
            self::Customer  => 3,
            self::Inactive  => 4,
            self::VIP       => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Lead      => 'Lead',
            self::Prospect  => 'Prospect',
            self::Customer  => 'Customer',
            self::Inactive  => 'Inactive',
            self::VIP       => 'VIP',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Lead      => 'blue',
            self::Prospect  => 'teal',
            self::Customer  => 'green',
            self::Inactive  => 'gray',
            self::VIP       => 'purple',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Lead      => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Prospect  => 'bg-teal-200 dark:text-white dark:bg-teal-900',
            self::Customer  => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Inactive  => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::VIP       => 'bg-purple-200 dark:text-white dark:bg-purple-900',
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
