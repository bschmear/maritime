<?php

namespace App\Domain\AssetUnit\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\Asset\Models\Asset;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\Customer\Models\Customer;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Location\Models\Location;
use App\Domain\InventoryImage\Models\InventoryImage;
use App\Models\Concerns\HasDocuments;

class AssetUnit extends Model
{
    use HasDocuments;

    protected $fillable = [
        'asset_id',
        'serial_number',
        'hin',
        'sku',
        'condition',
        'status',
        'inactive',
        'is_customer_owned',
        'is_consignment',
        'engine_hours',
        'last_service_at',
        'warranty_expires_at',
        'cost',
        'asking_price',
        'sold_price',
        'price_history',
        'vendor_id',
        'customer_id',
        'location_id',
        'subsidiary_id',
        'in_service_at',
        'out_of_service_at',
        'sold_at',
        'attributes',
        'notes',
    ];

    protected $casts = [
        'condition' => 'integer',
        'status' => 'integer',
        'inactive' => 'boolean',
        'is_customer_owned' => 'boolean',
        'is_consignment' => 'boolean',
        'engine_hours' => 'decimal:1',
        'last_service_at' => 'date',
        'warranty_expires_at' => 'date',
        'cost' => 'decimal:2',
        'asking_price' => 'decimal:2',
        'sold_price' => 'decimal:2',
        'price_history' => 'array',
        'in_service_at' => 'datetime',
        'out_of_service_at' => 'datetime',
        'sold_at' => 'datetime',
        'attributes' => 'array',
    ];

    /**
     * Attributes to append to model's array/JSON form
     */
    protected $appends = ['display_name'];

    /**
     * Unit belongs to an inventory item
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    public function subsidiary()
    {
        return $this->belongsTo(Subsidiary::class, 'subsidiary_id');
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
     * Format: Asset Name - Unit Identifier
     */
    public function getDisplayNameAttribute()
    {
        $assetName = $this->asset?->display_name ?? 'Unknown Asset';
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

        return "{$assetName} - {$unitIdentifier}";
    }

    public function images()
    {
        return $this->morphMany(InventoryImage::class, 'imageable');
    }

}
