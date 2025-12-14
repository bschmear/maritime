<?php

namespace App\Domain\InventoryItem\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    // Fillable fields for mass assignment
    protected $fillable = [
        'type',
        'sku',
        'display_name',
        'slug',
        'make',
        'model',
        'year',
        'length',
        'boat_type',
        'engine_details',
        'attributes',
        'photos',
        'videos',
        'default_cost',
        'default_price',
        'description',
        'inactive',
    ];

    // Cast JSON and other fields
    protected $casts = [
        'attributes' => 'array',
        'photos' => 'array',
        'videos' => 'array',
        'inactive' => 'boolean',
        'default_cost' => 'decimal:2',
        'default_price' => 'decimal:2',
    ];

    // Automatically generate slug from display_name if not provided
    public static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (isset($item->slug) && empty($item->slug) && !empty($item->display_name)) {
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
