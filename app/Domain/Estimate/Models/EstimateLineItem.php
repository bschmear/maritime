<?php

namespace App\Domain\Estimate\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\AssetVariant\Models\AssetVariant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EstimateLineItem extends Model
{
    protected $table = 'estimate_line_items';

    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'quantity' => 'integer',
        'position' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function version(): BelongsTo
    {
        return $this->belongsTo(EstimateVersion::class, 'estimate_version_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assetVariant(): BelongsTo
    {
        return $this->belongsTo(AssetVariant::class, 'asset_variant_id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(EstimateLineItemAddon::class, 'estimate_line_item_id');
    }
}
