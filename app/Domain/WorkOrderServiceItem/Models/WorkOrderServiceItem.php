<?php

namespace App\Domain\WorkOrderServiceItem\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderServiceItem extends Model
{
    protected $table = 'work_order_service_items';

    protected $fillable = [
        'work_order_id',
        'service_item_id',
        'display_name',
        'description',
        'quantity',
        'unit_price',
        'unit_cost',
        'total_price',
        'total_cost',
        'estimated_hours',
        'actual_hours',
        'billable',
        'warranty',
        'inactive',
        'sort_order',
        'attributes',
        'billing_type',
    ];

    protected $casts = [
        'billing_type'    => 'integer',

        'quantity'        => 'decimal:2',
        'unit_price'      => 'decimal:2',
        'unit_cost'       => 'decimal:2',
        'total_price'     => 'decimal:2',
        'total_cost'      => 'decimal:2',

        'estimated_hours' => 'decimal:2',
        'actual_hours'    => 'decimal:2',

        'billable'        => 'boolean',
        'warranty'        => 'boolean',
        'inactive'        => 'boolean',

        'attributes'      => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Model Events
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        // Calculations are now handled by WorkOrderCalculator service
        // This ensures all pricing logic is centralized and consistent
    }

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
    | Calculated Helpers
    |--------------------------------------------------------------------------
    */

    public function getProfitAttribute(): float
    {
        return (float) ($this->total_price ?? 0) - (float) ($this->total_cost ?? 0);
    }

    public function getMarginPercentAttribute(): float
    {
        if (!$this->total_price || $this->total_price == 0) {
            return 0;
        }

        return round(
            (($this->profit / $this->total_price) * 100),
            2
        );
    }

    public function getIsLaborAttribute(): bool
    {
        // Hourly billing type (1 = hourly, 2 = flat, 3 = quantity)
        return $this->billing_type === 1;
    }
}
