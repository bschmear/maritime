<?php

namespace App\Domain\Asset\Models;

use App\Domain\AssetSpec\Models\AssetSpecValue;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\InventoryImage\Models\InventoryImage;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Asset extends Model
{
    use HasDocuments;

    protected $fillable = [
        'type',
        'display_name',
        'slug',
        'catalog_asset_key',
        'hin',
        'serial_number',
        'subsidiary_id',
        'location_id',
        'customer_id',
        'status',
        'condition',
        'inactive',
        'in_service_at',
        'out_of_service_at',
        'sold_at',
        'engine_hours',
        'last_service_at',
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
        'purchase_cost',
        'sale_price',
        'purchase_date',
        'default_cost',
        'default_price',
        'attributes',
        'description',
        'has_variants',
    ];

    protected $casts = [
        'attributes' => 'array',
        'inactive' => 'boolean',
        'has_variants' => 'boolean',
        'persons' => 'integer',
        'minimum_power' => 'integer',
        'maximum_power' => 'integer',
        'default_cost' => 'decimal:2',
        'default_price' => 'decimal:2',
        'purchase_date' => 'date',
    ];

    public function units()
    {
        return $this->hasMany(AssetUnit::class);
    }

    public function variants()
    {
        return $this->hasMany(AssetVariant::class);
    }

    public function hasVariants(): bool
    {
        return (bool) $this->has_variants;
    }

    public function make()
    {
        return $this->belongsTo(BoatMake::class, 'make_id', 'id');
    }

    public function boat_makes()
    {
        return $this->belongsTo(BoatMake::class, 'make_id', 'id');
    }

    public function images()
    {
        return $this->morphMany(InventoryImage::class, 'imageable');
    }

    public function specs(): MorphMany
    {
        return $this->specValues();
    }

    public function specValues(): MorphMany
    {
        return $this->morphMany(AssetSpecValue::class, 'specable')->with('definition');
    }

    /**
     * Automatically generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(callback: function ($item) {
            if (! empty($item->catalog_asset_key)) {
                $item->slug = $item->catalog_asset_key;

                return;
            }
            if (empty($item->slug) && ! empty($item->display_name)) {
                $item->slug = strtolower(str_replace(' ', '-', $item->display_name));
            }
        });

        static::updating(function ($item) {
            if (! empty($item->catalog_asset_key)) {
                $item->slug = $item->catalog_asset_key;

                return;
            }
            if (! empty($item->display_name)) {
                $item->slug = strtolower(str_replace(' ', '-', $item->display_name));
            }
        });

        static::deleting(function (Asset $asset) {
            $asset->specValues()->delete();
        });
    }
}
