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
        static::saving(function ($model) {
            $quantity = $model->quantity ?? 1;
            $rate = $model->unit_price ?? 0;
            $cost = $model->unit_cost ?? 0;
            $actualHours = $model->actual_hours ?? 0;

            // Calculate total_price (revenue) based on billing type
            switch ($model->billing_type) {
                case 2: // Flat Rate
                    $model->total_price = $rate;
                    break;
                case 1: // Hourly
                    $model->total_price = $actualHours * $rate;
                    break;
                case 3: // Quantity
                default:
                    $model->total_price = $quantity * $rate;
                    break;
            }

            // Calculate total_cost based on billing type
            switch ($model->billing_type) {
                case 2: // Flat Rate
                    $model->total_cost = $cost;
                    break;
                case 1: // Hourly
                default:
                    $model->total_cost = $cost;
                    break;
            }
        });
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
