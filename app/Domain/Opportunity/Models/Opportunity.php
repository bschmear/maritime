<?php

namespace App\Domain\Opportunity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Opportunity extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }

            if (empty($record->sequence)) {
                $max = \Illuminate\Support\Facades\DB::table('opportunities')->max('sequence') ?? 0;
                $record->sequence = (int) $max + 1;
            }
        });
    }

    protected $table = 'opportunities';

    /**
     * Allow mass-assignment for all fields except id
     */
    protected $guarded = ['id'];

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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    public function inventoryItems(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\InventoryItem\Models\InventoryItem::class,
            'inventory_item_opportunity', // pivot table
            'opportunity_id',
            'inventory_item_id'
        )->withPivot('quantity', 'notes')->withTimestamps();
    }

    public function inventory_items(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\InventoryItem\Models\InventoryItem::class,
            'inventory_item_opportunity', // pivot table
            'opportunity_id',
            'inventory_item_id'
        )->withPivot('quantity', 'notes')->withTimestamps();
    }
}
