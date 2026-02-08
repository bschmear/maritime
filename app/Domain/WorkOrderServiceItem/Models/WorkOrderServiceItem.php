<?php

namespace App\Domain\WorkOrder\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderServiceItem extends Model
{
    protected $table = 'work_order_service_items';

    protected $fillable = [
        'work_order_id',
        'service_item_id',

        // Snapshot fields
        'display_name',
        'description',

        // Pricing
        'quantity',
        'unit_price',
        'unit_cost',

        // Labor
        'estimated_hours',
        'actual_hours',

        // Flags
        'billable',
        'warranty',

        // Ordering / meta
        'sort_order',
        'attributes',
    ];

    protected $casts = [
        'quantity'         => 'decimal:2',
        'unit_price'       => 'decimal:2',
        'unit_cost'        => 'decimal:2',
        'estimated_hours'  => 'decimal:2',
        'actual_hours'     => 'decimal:2',

        'billable' => 'boolean',
        'warranty' => 'boolean',
        'attributes' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function workOrder()
    {
        return $this->belongsTo(
            \App\Domain\WorkOrder\Models\WorkOrder::class
        );
    }

    public function serviceItem()
    {
        return $this->belongsTo(
            \App\Domain\ServiceItem\Models\ServiceItem::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Calculated helpers
    |--------------------------------------------------------------------------
    */

    public function getTotalAttribute(): float
    {
        return ($this->quantity ?? 1) * ($this->unit_price ?? 0);
    }

    public function getLaborTotalAttribute(): float
    {
        return ($this->actual_hours ?? 0) * ($this->unit_price ?? 0);
    }
}
