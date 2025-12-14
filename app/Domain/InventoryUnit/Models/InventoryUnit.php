<?php

namespace App\Domain\InventoryUnit\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\InventoryItem\Models\InventoryItem;
use App\Models\User;
use App\Models\Location;

class InventoryUnit extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'serial_number',
        'hull_id',
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
     * Unit belongs to an inventory item
     */
    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Optional vendor (consignment)
     */
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Physical location of the unit
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
