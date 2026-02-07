<?php

namespace App\Domain\InventoryItem\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\InventoryUnit\Models\InventoryUnit;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\InventoryImage\Models\InventoryImage;
use App\Models\Concerns\HasDocuments;

class InventoryItem extends Model
{
    use HasDocuments;

    protected $fillable = [
        'type',
        'sku',
        'display_name',
        'slug',
        'boat_type',
        'boat_make_id',
        'model',
        'year',
        'length',
        'engine_details',
        'attributes',
        'photos',
        'videos',
        'default_cost',
        'default_price',
        'description',
        'inactive',
    ];

    protected $casts = [
        'attributes' => 'array',
        'photos' => 'array',
        'videos' => 'array',
        'inactive' => 'boolean',
        'default_cost' => 'decimal:2',
        'default_price' => 'decimal:2',
    ];

    /**
     * Inventory item has many units
     * (ex: 5 of the same boat, or 20 parts in stock)
     */
    public function units()
    {
        return $this->hasMany(InventoryUnit::class);
    }

    public function boat_make()
    {
        return $this->belongsTo(BoatMake::class, 'boat_make_id', 'id');
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
