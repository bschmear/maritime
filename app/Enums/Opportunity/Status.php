<?php

namespace App\Enums\Opportunity;

enum Status: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
    case OnHold = 'on_hold';

    public function id(): int
    {
        return match ($this) {
            self::Open => 1,
            self::Won => 2,
            self::Lost => 3,
            self::OnHold => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::OnHold => 'On Hold',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Won => 'green',
            self::Lost => 'red',
            self::OnHold => 'yellow',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Won => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Lost => 'bg-red-200 dark:text-white dark:bg-red-900',
            self::OnHold => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
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
