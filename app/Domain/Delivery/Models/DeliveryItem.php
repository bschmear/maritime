<?php

namespace App\Domain\Delivery\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DeliveryItem extends Model
{
    protected $table = 'delivery_items';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'taxable' => 'boolean',
        'tax_rate' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    protected $appends = ['display_name'];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function asset_unit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function assetVariant(): BelongsTo
    {
        return $this->belongsTo(AssetVariant::class, 'asset_variant_id');
    }

    public function asset_variant(): BelongsTo
    {
        return $this->belongsTo(AssetVariant::class, 'asset_variant_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    public function delivered_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    /**
     * Prefer a human-readable label: the unit's display_name, falling back to variant or stored `name`.
     */
    public function getDisplayNameAttribute(): string
    {
        $unit = $this->relationLoaded('assetUnit') ? $this->assetUnit : null;
        if ($unit && method_exists($unit, 'getDisplayNameAttribute')) {
            $name = $unit->display_name;
            if ($name) {
                return (string) $name;
            }
        }

        $variant = $this->relationLoaded('assetVariant') ? $this->assetVariant : null;
        if ($variant && ! empty($variant->display_name)) {
            return (string) $variant->display_name;
        }

        return (string) ($this->attributes['name'] ?? 'Item');
    }
}
