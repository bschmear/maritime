<?php

namespace App\Domain\InventoryItem\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\InventoryUnit\Models\InventoryUnit;

class InventoryItem extends Model
{
    protected $fillable = [
        'type',
        'sku',
        'display_name',
        'slug',
        'boat_type',
        'make',
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

    /**
     * Automatically generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
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
