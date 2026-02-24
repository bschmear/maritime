<?php

namespace App\Enums\Deliveries;

enum ChecklistCategory: string
{
    case PreDelivery   = 'pre_delivery';
    case UponDelivery  = 'upon_delivery';

    public function id(): string
    {
        return match ($this) {
            self::PreDelivery  => 'pre_delivery',
            self::UponDelivery => 'upon_delivery',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PreDelivery  => 'Pre Delivery',
            self::UponDelivery => 'Upon Delivery',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PreDelivery  => 'blue',
            self::UponDelivery => 'green',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::PreDelivery  => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::UponDelivery => 'bg-green-200 dark:text-white dark:bg-green-900',
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