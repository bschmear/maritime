<?php

namespace App\Enums\Leads;

enum Status: string
{
    case Open          = 'open';
    case Contacted     = 'contacted';
    case Qualified     = 'qualified';
    case Converted     = 'converted';
    case Disqualified  = 'disqualified';

    public function id(): int
    {
        return match ($this) {
            self::Open         => 1,
            self::Contacted    => 2,
            self::Qualified    => 3,
            self::Converted    => 4,
            self::Disqualified => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Open         => 'Open',
            self::Contacted    => 'Contacted',
            self::Qualified    => 'Qualified',
            self::Converted    => 'Converted',
            self::Disqualified => 'Disqualified',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open         => 'blue',
            self::Contacted    => 'teal',
            self::Qualified    => 'green',
            self::Converted    => 'purple',
            self::Disqualified => 'gray',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Open         => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Contacted    => 'bg-teal-200 dark:text-white dark:bg-teal-900',
            self::Qualified    => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Converted    => 'bg-purple-200 dark:text-white dark:bg-purple-900',
            self::Disqualified => 'bg-gray-200 dark:text-white dark:bg-gray-900',
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
