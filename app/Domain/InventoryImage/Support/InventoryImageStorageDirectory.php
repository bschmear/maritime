<?php

declare(strict_types=1);

namespace App\Domain\InventoryImage\Support;

use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;

final class InventoryImageStorageDirectory
{
    public const DEFAULT = 'inventory/images';

    public static function forType(string $imageableType): string
    {
        return match ($imageableType) {
            ServiceTicket::class => 'servicetickets',
            WarrantyClaim::class => 'warrantyclaim',
            default => self::DEFAULT,
        };
    }
}
