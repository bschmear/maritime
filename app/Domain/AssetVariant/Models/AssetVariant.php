<?php

namespace App\Domain\AssetVariant\Models;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Domain\AssetUnit\Models\AssetUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AssetVariant extends Model
{
    protected $table = 'asset_variants';

    protected $fillable = [
        'asset_id',
        'name',
        'display_name',
        'length',
        'width',
        'key',
        'default_cost',
        'default_price',
        'description',
        'inactive',
    ];

    protected $casts = [
        'length' => 'integer',
        'width' => 'integer',
        'default_cost' => 'decimal:2',
        'default_price' => 'decimal:2',
        'inactive' => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(AssetUnit::class, 'asset_variant_id');
    }

    public function specValues(): MorphMany
    {
        return $this->morphMany(AssetSpecValue::class, 'specable')->with('definition');
    }

    /**
     * Variant-specific description if set; otherwise the parent asset's description.
     */
    public function resolvedDescription(): ?string
    {
        if (is_string($this->description) && trim($this->description) !== '') {
            return trim($this->description);
        }

        $asset = $this->relationLoaded('asset') ? $this->asset : $this->asset()->first();
        $d = $asset?->description;
        if ($d === null || trim((string) $d) === '') {
            return null;
        }

        return trim((string) $d);
    }

    protected static function booted(): void
    {
        static::saving(function (AssetVariant $variant): void {
            $parts = array_filter([
                $variant->name,
            ], fn ($v) => $v !== null && $v !== '');

            $variant->display_name = $parts !== []
                ? implode(' ', $parts)
                : ($variant->exists ? 'Variant #'.$variant->id : 'Variant');
        });

        static::deleting(function (AssetVariant $variant): void {
            $variant->specValues()->delete();
        });
    }
}
