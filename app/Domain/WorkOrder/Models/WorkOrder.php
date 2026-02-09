<?php

namespace App\Domain\WorkOrder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\WorkOrder\Models\WorkOrderLineItem;
use App\Domain\InventoryImage\Models\InventoryImage;

class WorkOrder extends Model
{
    protected $table = 'work_orders';

    /**
     * Mass assignable fields
     * Keep this permissive since you plan for dynamic/custom fields
     */
    protected $guarded = ['id'];

    /**
     * Casts
     */
    protected $casts = [
        'status' => 'integer',
        'priority' => 'integer',
        'type' => 'integer',

        'billable' => 'boolean',
        'draft' => 'boolean',
        'warranty' => 'boolean',

        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function subsidiary(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\Subsidiary\Models\Subsidiary::class
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\Customer\Models\Customer::class
        );
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\AssetUnit\Models\AssetUnit::class
        );
    }

    public function asset_unit(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\AssetUnit\Models\AssetUnit::class
        );
    }

    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\InventoryUnit\Models\InventoryUnit::class
        );
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\InventoryItem\Models\InventoryItem::class
        );
    }

    public function inventory_item(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\InventoryItem\Models\InventoryItem::class,
            'inventory_item_id'
        );
    }

    public function images()
    {
        return $this->morphMany(InventoryImage::class, 'imageable');
    }

    public function serviceItems()
    {
        return $this->hasMany(
            \App\Domain\WorkOrder\Models\WorkOrderServiceItem::class
        );
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'assigned_user_id'
        );
    }

    public function assigned_user(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'assigned_user_id'
        );
    }

    public function requested_by_user(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'requested_by_user_id'
        );
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\Location\Models\Location::class
        );
    }

    // public function lineItems(): HasMany
    // {
    //     return $this->hasMany(
    //         WorkOrderLineItem::class
    //     );
    // }

    /*
    |--------------------------------------------------------------------------
    | Helpers / Derived Values
    |--------------------------------------------------------------------------
    */

    public function recalculateTotals(): void
    {
        $labor = $this->lineItems()
            ->where('type', LineItemType::LABOR)
            ->sum('total_cost');

        $parts = $this->lineItems()
            ->where('type', LineItemType::PART)
            ->sum('total_cost');

        $this->labor_cost = $labor;
        $this->parts_cost = $parts;
        $this->total_cost = $labor + $parts;

        $this->saveQuietly();
    }
}
