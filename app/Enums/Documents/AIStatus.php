<?php

namespace App\Enums\Documents;

enum AIStatus: string
{
    case Pending   = 'pending';
    case Processing    = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function id(): string
    {
        return match ($this) {
            self::Pending   => 'pending',
            self::Processing    => 'processing',
            self::Completed => 'completed',
            self::Failed => 'failed',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pending',
            self::Processing    => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending   => 'gray',
            self::Processing    => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Pending   => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::Processing    => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Completed => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Failed => 'bg-red-200 dark:text-white dark:bg-red-900',
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
