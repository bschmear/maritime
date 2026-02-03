<?php

namespace App\Enums\Locations;

enum LocationType: string
{
    case Dealership = 'dealership';
    case Marina = 'marina';
    case Service = 'service';
    case Storage = 'storage';
    case Warehouse = 'warehouse';
    case Storefront = 'storefront';
    case Parts = 'parts';
    case Corporate = 'corporate';
    case Other = 'other';

    public function id(): int
    {
        return match ($this) {
            self::Dealership => 1,
            self::Marina => 2,
            self::Service => 3,
            self::Storage => 4,
            self::Warehouse => 5,
            self::Storefront => 6,
            self::Parts => 7,
            self::Corporate => 8,
            self::Other => 9,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Dealership => 'Dealership',
            self::Marina => 'Marina',
            self::Service => 'Service Center',
            self::Storage => 'Storage Facility',
            self::Warehouse => 'Warehouse',
            self::Storefront => 'Retail Storefront',
            self::Parts => 'Parts Department',
            self::Corporate => 'Corporate / HQ',
            self::Other => 'Other',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
