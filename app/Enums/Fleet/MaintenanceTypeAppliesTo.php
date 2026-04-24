<?php

declare(strict_types=1);

namespace App\Enums\Fleet;

enum MaintenanceTypeAppliesTo: string
{
    case All = 'all';
    case Truck = 'truck';
    case Trailer = 'trailer';

    /**
     * @return list<array{id: string, name: string}>
     */
    public static function options(): array
    {
        return [
            ['id' => self::All->value, 'name' => 'All fleet (general)'],
            ['id' => self::Truck->value, 'name' => 'Trucks only'],
            ['id' => self::Trailer->value, 'name' => 'Trailers only'],
        ];
    }
}
