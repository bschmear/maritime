<?php

namespace App\Domain\BoatMake\Models;

use App\Domain\InventoryItem\Models\InventoryItem;
use Illuminate\Database\Eloquent\Model;

class BoatMake extends Model
{
    protected $table = 'boat_make';

    protected $fillable = [
        'display_name',
        'slug',
        'is_custom',
        'logo',
        'active',
        'asset_types',
        'brand_key',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
        'active' => 'boolean',
        'asset_types' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(InventoryItem::class, 'boat_make_id', 'id');
    }
}
