<?php

declare(strict_types=1);

namespace App\Enums\ServiceTicketServiceItem;

enum WarrantyCoverageType: string
{
    case Dealership = 'dealership';
    case Manufacturer = 'manufacturer';

    public function label(): string
    {
        return match ($this) {
            self::Dealership => 'Dealership warranty',
            self::Manufacturer => 'Manufacturer warranty',
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
