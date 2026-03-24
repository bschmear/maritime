<?php

namespace App\Enums\Communication;

enum Status: string
{
    case Open = 'open';
    case Waiting = 'waiting';
    case Closed = 'closed';

    public function id(): int
    {
        return match ($this) {
            self::Open => 1,
            self::Waiting => 2,
            self::Closed => 3,
        };
    }

    public static function fromId(int $id): ?self
    {
        return match ($id) {
            1 => self::Open,
            2 => self::Waiting,
            3 => self::Closed,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Waiting => 'Waiting',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'green',
            self::Waiting => 'yellow',
            self::Closed => 'gray',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Open => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Waiting => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
            self::Closed => 'bg-gray-200 dark:text-white dark:bg-gray-900',
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
