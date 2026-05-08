<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCatalogAsset extends Model
{
    protected $connection = 'inventory';

    protected $table = 'assets';

    protected $fillable = [
        'type',
        'display_name',
        'slug',
        'inactive',
        'make_id',
        'model',
        'year',
        'length_mm',
        'width_mm',
        'height_mm',
        'weight_kg',
        'capacity_persons',
        'max_hp',
        'fuel_capacity_l',
        'engine_shaft',
        'water_tank',
        'category',
        'engine_details',
        'attributes',
        'catalog_data',
        'features',
        'description',
        'default_cost',
        'default_price',
        'has_variants',
    ];

    protected $casts = [
        'inactive' => 'boolean',
        'has_variants' => 'boolean',
        'length_mm' => 'integer',
        'width_mm' => 'integer',
        'height_mm' => 'integer',
        'weight_kg' => 'integer',
        'capacity_persons' => 'integer',
        'max_hp' => 'integer',
        'fuel_capacity_l' => 'integer',
        'default_cost' => 'decimal:2',
        'default_price' => 'decimal:2',
        'attributes' => 'array',
        'catalog_data' => 'array',
        'features' => 'array',
    ];

    public function make(): BelongsTo
    {
        return $this->belongsTo(InventoryBoatMake::class, 'make_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(InventoryCatalogAssetVariant::class, 'asset_id');
    }
}
