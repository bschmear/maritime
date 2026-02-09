<?php

namespace App\Domain\Asset\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\InventoryImage\Models\InventoryImage;
use App\Models\Concerns\HasDocuments;

class Asset extends Model
{
    use HasDocuments;

    protected $fillable = [
        'type',
        'display_name',
        'slug',
        'hin',
        'serial_number',
        'subsidiary_id',
        'location_id',
        'customer_id',
        'status',
        'condition',
        'inactive',
        'in_service_at',
        'out_of_service_at',
        'sold_at',
        'engine_hours',
        'last_service_at',
        'make_id',
        'model',
        'year',
        'length',
        'beam',
        'persons',
        'minimum_power',
        'maximum_power',
        'fuel_tank',
        'engine_shaft',
        'water_tank',
        'category',
        'engine_details',
        'purchase_cost',
        'sale_price',
        'purchase_date',
        'default_cost',
        'default_price',
        'attributes',
        'description',
    ];

    protected $casts = [
        'attributes' => 'array',
        'inactive' => 'boolean',
        'persons' => 'integer',
        'minimum_power' => 'integer',
        'maximum_power' => 'integer',
        'default_cost' => 'decimal:2',
        'default_price' => 'decimal:2',
        'purchase_date' => 'date',
    ];

    public function units()
    {
        return $this->hasMany(AssetUnit::class);
    }

    public function make()
    {
        return $this->belongsTo(BoatMake::class, 'make_id', 'id');
    }

    public function images()
    {
        return $this->morphMany(InventoryImage::class, 'imageable');
    }

    /**
     * Automatically generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(callback: function ($item) {
            if (empty($item->slug) && !empty($item->display_name)) {
                $item->slug = strtolower(str_replace(' ', '-', $item->display_name));
            }
        });

        static::updating(function ($item) {
            if (!empty($item->display_name)) {
                $item->slug = strtolower(str_replace(' ', '-', $item->display_name));
            }
        });
    }

}
