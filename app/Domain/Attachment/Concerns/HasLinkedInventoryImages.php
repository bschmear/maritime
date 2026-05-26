<?php

declare(strict_types=1);

namespace App\Domain\Attachment\Concerns;

use App\Domain\InventoryImage\Models\InventoryImage;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Inventory images for ServiceTicket, WorkOrder, and WarrantyClaim are exposed via attachment_links
 * (one file, many attachable contexts).
 */
trait HasLinkedInventoryImages
{
    /**
     * @return BelongsToMany<InventoryImage, $this>
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(InventoryImage::class, 'attachment_links', 'attachable_id', 'inventory_image_id')
            ->where('attachment_links.attachable_type', static::class)
            ->withPivot(['id', 'sort_order', 'is_primary', 'visible_to_customer'])
            ->orderBy('attachment_links.sort_order')
            ->orderBy('attachment_links.id');
    }
}
