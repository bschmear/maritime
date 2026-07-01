<?php

declare(strict_types=1);

namespace App\Enums\Shipment;

enum Status: string
{
    case Draft = 'draft';
    case Rated = 'rated';
    case Purchased = 'purchased';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Rated => 'Rated',
            self::Purchased => 'Purchased',
            self::InTransit => 'In transit',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }
}
