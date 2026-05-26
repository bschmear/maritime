<?php

namespace App\Enums\Transaction;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function id(): int
    {
        return match ($this) {
            self::Pending => 1,
            self::Processing => 2,
            self::Completed => 3,
            self::Failed => 4,
            self::Cancelled => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
            self::Cancelled => 'slate',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-200 dark:text-white dark:bg-gray-800',
            self::Processing => 'bg-blue-100 dark:text-white dark:bg-blue-900',
            self::Completed => 'bg-green-100 dark:text-white dark:bg-green-900',
            self::Failed => 'bg-red-100 dark:text-white dark:bg-red-900',
            self::Cancelled => 'bg-slate-200 dark:text-white dark:bg-slate-800',
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
