<?php

namespace App\Enums\Communication;

enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function id(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
        };
    }

    public static function fromId(int $id): ?self
    {
        return match ($id) {
            1 => self::Low,
            2 => self::Medium,
            3 => self::High,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'green',
            self::Medium => 'yellow',
            self::High => 'red',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Low => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Medium => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
            self::High => 'bg-red-200 dark:text-white dark:bg-red-900',
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

    public static function selectOptions(): array
    {
        return array_map(fn (self $case) => [
            $case->id() => $case->label(),
        ], self::cases());
    }
}
