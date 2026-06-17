<?php

namespace App\Enums\Deliveries;

enum Status: string
{
    case Requested   = 'requested';
    case Scheduled   = 'scheduled';
    case EnRoute     = 'en_route';
    case Delivered   = 'delivered';
    case Cancelled   = 'cancelled';
    case Rescheduled = 'rescheduled';

    public function id(): string
    {
        return match ($this) {
            self::Requested   => 'requested',
            self::Scheduled   => 'scheduled',
            self::EnRoute     => 'en_route',
            self::Delivered   => 'delivered',
            self::Cancelled   => 'cancelled',
            self::Rescheduled => 'rescheduled',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Requested   => 'Requested',
            self::Scheduled   => 'Scheduled',
            self::EnRoute     => 'En Route',
            self::Delivered   => 'Delivered',
            self::Cancelled   => 'Cancelled',
            self::Rescheduled => 'Rescheduled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested   => 'amber',
            self::Scheduled   => 'gray',
            self::EnRoute     => 'blue',
            self::Delivered   => 'green',
            self::Cancelled   => 'red',
            self::Rescheduled => 'yellow',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Requested   => 'bg-amber-200 dark:text-white dark:bg-amber-900',
            self::Scheduled   => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::EnRoute     => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Delivered   => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Cancelled   => 'bg-red-200 dark:text-white dark:bg-red-900',
            self::Rescheduled => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id'      => $case->id(),
            'value'   => $case->value,
            'name'    => $case->label(),
            'color'   => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }

    /** @return list<string> */
    public static function operationalValues(): array
    {
        return [
            self::Scheduled->value,
            self::EnRoute->value,
            self::Delivered->value,
            self::Cancelled->value,
            self::Rescheduled->value,
        ];
    }
}
