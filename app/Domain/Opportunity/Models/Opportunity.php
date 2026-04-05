<?php

namespace App\Domain\Opportunity\Models;

use App\Domain\AssetVariant\Models\AssetVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Opportunity extends Model
{
    use SoftDeletes;

    protected $table = 'opportunities';

    /**
     * Never mass-assign audit/system columns (form sends empty strings for read-only datetimes otherwise).
     */
    protected $guarded = ['id', 'uuid', 'sequence', 'created_at', 'updated_at'];

    /**
     * Casts
     */
    protected $casts = [
        'needs_engine' => 'boolean',
        'needs_trailer' => 'boolean',
        'estimated_value' => 'decimal:2',
        'opened_at' => 'datetime',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
    ];

    protected $appends = ['display_name'];

    protected static function booted()
    {
        static::creating(function ($record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
            if (empty($record->sequence)) {
                $next = (int) (DB::table('opportunities')->max('sequence') ?? 999);
                $record->sequence = $next + 1;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Customer\Models\Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Lead\Models\Lead::class);
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Qualification\Models\Qualification::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'createdby_id');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\Asset\Models\Asset::class,
            'asset_opportunity',
            'opportunity_id',
            'asset_id'
        )->withPivot('quantity', 'unit_price', 'estimated_cost', 'notes', 'asset_variant_id')->withTimestamps();
    }

    /**
     * Eager-load pivot asset_variant_id labels for JSON (Inertia).
     *
     * @param  Collection<int, \App\Domain\Asset\Models\Asset>  $assets
     */
    public static function hydratePivotAssetVariants(Collection $assets): void
    {
        if ($assets->isEmpty()) {
            return;
        }

        $ids = $assets->pluck('pivot.asset_variant_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $map = AssetVariant::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($assets as $asset) {
            $vid = $asset->pivot->asset_variant_id ?? null;
            if ($vid && $map->has($vid)) {
                $asset->setRelation('asset_variant', $map->get($vid));
            }
        }
    }

    public function inventoryItems(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\InventoryItem\Models\InventoryItem::class,
            'inventory_item_opportunity',
            'opportunity_id',
            'inventory_item_id'
        )->withPivot('quantity', 'unit_price', 'estimated_cost', 'notes')->withTimestamps();
    }

    public function inventory_items(): BelongsToMany
    {
        return $this->inventoryItems();
    }

    public function getDisplayNameAttribute()
    {
        return 'OPP-'.($this->sequence ?: $this->id ?: '???');
    }
}
