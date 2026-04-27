<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBoatMake extends Model
{
    protected $connection = 'inventory';

    protected $table = 'boat_make';

    protected $fillable = [
        'display_name',
        'slug',
        'active',
        'boat_type_id',
        'hull_type_id',
        'hull_material_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function boatType(): BelongsTo
    {
        return $this->belongsTo(InventoryBoatType::class, 'boat_type_id');
    }

    public function hullType(): BelongsTo
    {
        return $this->belongsTo(InventoryHullType::class, 'hull_type_id');
    }

    public function hullMaterial(): BelongsTo
    {
        return $this->belongsTo(InventoryHullMaterial::class, 'hull_material_id');
    }

    public function catalogAssets(): HasMany
    {
        return $this->hasMany(InventoryCatalogAsset::class, 'make_id');
    }
}
