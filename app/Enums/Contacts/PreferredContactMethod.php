<?php

namespace App\Enums\Contacts;

enum PreferredContactMethod: string
{
    case Phone = 'phone';
    case Email = 'email';
    case SMS   = 'sms';

    public function id(): int
    {
        return match ($this) {
            self::Phone => 1,
            self::Email => 2,
            self::SMS   => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::SMS   => 'SMS',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Phone => 'blue',
            self::Email => 'green',
            self::SMS   => 'orange',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Phone => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Email => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::SMS   => 'bg-orange-200 dark:text-white dark:bg-orange-900',
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
