<?php

declare(strict_types=1);

namespace App\Enums\WarrantyClaim;

enum LineItemCostType: string
{
    case Quantity = 'quantity';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Quantity => 'Quantity × cost',
            self::Fixed => 'Fixed total',
        };
    }

    public function lineTotal(int $quantity, float $cost): float
    {
        return match ($this) {
            self::Fixed => round($cost, 2),
            self::Quantity => round(max(1, $quantity) * $cost, 2),
        };
    }

    /**
     * @return list<array{id: string, value: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'id' => $case->value,
                'value' => $case->value,
                'name' => $case->label(),
            ],
            self::cases()
        );
    }
}
