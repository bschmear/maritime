<?php

declare(strict_types=1);

namespace App\Enums\Fleet;

enum FuelType: string
{
    case Diesel = 'diesel';
    case Gasoline = 'gasoline';
    case Electric = 'electric';
    case Hybrid = 'hybrid';
    case Propane = 'propane';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Diesel => 'Diesel',
            self::Gasoline => 'Gasoline',
            self::Electric => 'Electric',
            self::Hybrid => 'Hybrid',
            self::Propane => 'Propane',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<int, array{id: string, value: string, label: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->value,
            'value' => $case->value,
            'label' => $case->label(),
            'name' => $case->label(),
        ], self::cases());
    }
}
