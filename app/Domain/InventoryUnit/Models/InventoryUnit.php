<?php

namespace App\Domain\InventoryUnit\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\InventoryItem\Models\InventoryItem;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\Location\Models\Location;
use App\Domain\InventoryImage\Models\InventoryImage;
use App\Models\Concerns\HasDocuments;

class InventoryUnit extends Model
{
    use HasDocuments;

    protected $fillable = [
        'inventory_item_id',
        'serial_number',
        'hin',
        'sku',
        'batch_number',
        'quantity',
        'condition',
        'status',
        'engine_hours',
        'cost',
        'asking_price',
        'price_history',
        'vendor_id',
        'owner_name',
        'location_id',
        'inactive',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'condition' => 'integer',
        'status' => 'integer',
        'engine_hours' => 'integer',
        'inactive' => 'boolean',
        'cost' => 'decimal:2',
        'asking_price' => 'decimal:2',
        'price_history' => 'array',
    ];

    /**
     * Attributes to append to model's array/JSON form
     */
    protected $appends = ['display_name'];

    /**
     * Unit belongs to an inventory item
     */
    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
    /**
     * Optional vendor (consignment)
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Physical location of the unit
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Generate a display name for the unit
     * Format: Inventory Item Name - Unit Identifier
     */
    public function getDisplayNameAttribute()
    {
        $itemName = $this->inventoryItem?->display_name ?? 'Unknown Item';
        $unitIdentifier = '';

        // Priority: Serial Number > Hull ID > SKU > "Unit #{id}"
        if (!empty($this->serial_number)) {
            $unitIdentifier = "SN: {$this->serial_number}";
        } elseif (!empty($this->hin)) {
            $unitIdentifier = "HIN: {$this->hin}";
        } elseif (!empty($this->sku)) {
            $unitIdentifier = "SKU: {$this->sku}";
        } else {
            $unitIdentifier = "Unit #{$this->id}";
        }

        return "{$itemName} - {$unitIdentifier}";
    }

    public function images()
    {
        return $this->morphMany(InventoryImage::class, 'imageable');
    }

}
