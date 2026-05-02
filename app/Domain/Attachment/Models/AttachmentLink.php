<?php

declare(strict_types=1);

namespace App\Domain\Attachment\Models;

use App\Domain\InventoryImage\Models\InventoryImage;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttachmentLink extends Model
{
    protected $table = 'attachment_links';

    protected $fillable = [
        'inventory_image_id',
        'attachable_type',
        'attachable_id',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function inventoryImage(): BelongsTo
    {
        return $this->belongsTo(InventoryImage::class, 'inventory_image_id');
    }

    /**
     * @return list<class-string<Model>>
     */
    public static function linkableMorphClasses(): array
    {
        return [
            ServiceTicket::class,
            WorkOrder::class,
            WarrantyClaim::class,
        ];
    }

    public static function usesLinksForMorphClass(?string $imageableType): bool
    {
        if ($imageableType === null || $imageableType === '') {
            return false;
        }

        return in_array($imageableType, self::linkableMorphClasses(), true);
    }

    public static function usesLinksForParentType(string $parentType): bool
    {
        $class = 'App\\Domain\\'.$parentType.'\\Models\\'.$parentType;

        return in_array($class, self::linkableMorphClasses(), true);
    }
}
