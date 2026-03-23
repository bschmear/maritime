<?php

namespace App\Enums\Transaction;

enum AddOnType: int
{
    case Universal = 1;
    case Asset = 2;
    case InventoryItem = 3;

    public function label(): string
    {
        return match ($this) {
            self::Universal => 'Universal',
            self::Asset => 'Asset',
            self::InventoryItem => 'Inventory Item',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Universal => 'gray',
            self::Asset => 'blue',
            self::InventoryItem => 'purple',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Universal => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::Asset => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::InventoryItem => 'bg-purple-200 dark:text-white dark:bg-purple-900',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->value,
            'value' => $case->value,
            'name' => $case->label(),
            'color' => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }

    public static function tryFromInt(?int $value): ?self
    {
        if ($value === null) {
            return self::Universal;
        }

        return self::tryFrom($value);
    }
}
