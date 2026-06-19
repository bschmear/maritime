<?php

declare(strict_types=1);

namespace App\Enums\Financing;

enum Status: string
{
    case Active = 'active';
    case PaidOff = 'paid_off';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::PaidOff => 'Paid off',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'blue',
            self::PaidOff => 'green',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Active => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
            self::PaidOff => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'name' => $case->label(),
            'color' => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }
}
