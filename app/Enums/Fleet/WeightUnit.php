<?php

declare(strict_types=1);

namespace App\Enums\Fleet;

enum WeightUnit: string
{
    case Pounds = 'lbs';
    case Kilograms = 'kg';

    public function label(): string
    {
        return match ($this) {
            self::Pounds => 'lbs',
            self::Kilograms => 'kg',
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
