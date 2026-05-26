<?php

declare(strict_types=1);

namespace App\Domain\ServiceTicket\Support;

use Illuminate\Support\Collection;

final class ServiceTicketPortalImages
{
    /**
     * @param  \Illuminate\Support\Collection<int, \App\Domain\InventoryImage\Models\InventoryImage>  $images
     * @return list<array<string, mixed>>
     */
    public static function forCustomer(Collection $images): array
    {
        return $images
            ->filter(fn ($img) => (bool) ($img->pivot?->visible_to_customer ?? false))
            ->map(fn ($img) => [
                'id' => $img->id,
                'display_name' => $img->display_name,
                'url' => $img->url,
                'is_primary' => (bool) ($img->pivot?->is_primary ?? $img->is_primary),
            ])
            ->values()
            ->all();
    }
}
