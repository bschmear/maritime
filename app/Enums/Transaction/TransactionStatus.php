<?php

namespace App\Enums\Transaction;

enum TransactionStatus: string
{
    case Active = 'active';
    case Won = 'won';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function id(): int
    {
        return match ($this) {
            self::Active => 1,
            self::Won => 2,
            self::Lost => 3,
            self::Cancelled => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'blue',
            self::Won => 'green',
            self::Lost => 'red',
            self::Cancelled => 'gray',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Active => 'bg-blue-100 dark:text-white dark:bg-blue-900',
            self::Won => 'bg-green-100 dark:text-white dark:bg-green-900',
            self::Lost => 'bg-red-100 dark:text-white dark:bg-red-900',
            self::Cancelled => 'bg-gray-200 dark:text-white dark:bg-gray-800',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
            'color' => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }

    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
