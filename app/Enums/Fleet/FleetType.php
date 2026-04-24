<?php

declare(strict_types=1);

namespace App\Enums\Fleet;

enum FleetType: string
{
    case Truck = 'truck';
    case Trailer = 'trailer';
}
