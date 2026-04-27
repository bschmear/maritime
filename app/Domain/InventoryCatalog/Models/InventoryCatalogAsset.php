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
        'attributes',
        'description',
        'default_cost',
        'default_price',
        'has_variants',
    ];

    protected $casts = [
        'inactive' => 'boolean',
        'has_variants' => 'boolean',
        'persons' => 'integer',
        'minimum_power' => 'integer',
        'maximum_power' => 'integer',
        'default_cost' => 'decimal:2',
        'default_price' => 'decimal:2',
        'attributes' => 'array',
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
