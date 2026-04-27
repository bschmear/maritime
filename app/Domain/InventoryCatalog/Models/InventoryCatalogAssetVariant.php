<?php

declare(strict_types=1);

namespace App\Domain\InventoryCatalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCatalogAssetVariant extends Model
{
    protected $connection = 'inventory';

    protected $table = 'asset_variants';

    protected $fillable = [
        'asset_id',
        'name',
        'display_name',
        'key',
        'inactive',
        'default_cost',
        'default_price',
        'description',
    ];

    protected $casts = [
        'inactive' => 'boolean',
        'default_cost' => 'decimal:2',
        'default_price' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryCatalogAsset::class, 'asset_id');
    }
}
