<?php

namespace App\Enums\Opportunity;

enum Stage: string
{
    case New = 'new';
    case InventoryConfirmed = 'inventory_confirmed';
    case Quoted = 'quoted';
    case Negotiation = 'negotiation';
    case Deposit = 'deposit';
    case Closing = 'closing';

    public function id(): int
    {
        return match ($this) {
            self::New => 1,
            self::InventoryConfirmed => 2,
            self::Quoted => 3,
            self::Negotiation => 4,
            self::Deposit => 5,
            self::Closing => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InventoryConfirmed => 'Inventory Confirmed',
            self::Quoted => 'Quoted',
            self::Negotiation => 'Negotiation',
            self::Deposit => 'Deposit',
            self::Closing => 'Closing',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::InventoryConfirmed => 'blue',
            self::Quoted => 'purple',
            self::Negotiation => 'orange',
            self::Deposit => 'yellow',
            self::Closing => 'green',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::New => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::InventoryConfirmed => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Quoted => 'bg-purple-200 dark:text-white dark:bg-purple-900',
            self::Negotiation => 'bg-orange-200 dark:text-white dark:bg-orange-900',
            self::Deposit => 'bg-yellow-200 dark:text-white dark:bg-yellow-900',
            self::Closing => 'bg-green-200 dark:text-white dark:bg-green-900',
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
