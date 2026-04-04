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
