<?php

namespace App\Domain\InventoryItem\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    // Fillable fields for mass assignment
    protected $fillable = [
        'type',              // Item type (boat, part, accessory, service)
        'sku',               // Stock keeping unit
        'display_name',      // Name of the item
        'slug',              // URL-friendly identifier
        'make',              // Boat manufacturer
        'model',             // Boat model
        'year',              // Model year
        'length',            // Boat length
        'engine_details',    // Engine information
        'attributes',        // JSON field for custom attributes
        'photos',            // JSON array of photo URLs
        'videos',            // JSON array of video URLs
        'default_cost',      // Default cost
        'default_price',     // Default price
        'description',       // Description
        'inactive',          // Boolean flag for inactive items
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
