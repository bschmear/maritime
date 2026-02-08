<?php

namespace App\Domain\ServiceItem\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceItem extends Model
{
    protected $table = 'service_items';

    /**
     * Mass assignable fields
     */
    protected $guarded = ['id'];

    /**
     * Casts
     */
    protected $casts = [
        'billing_type' => 'integer',
        'default_rate' => 'decimal:2',
        'default_cost' => 'decimal:2',

        'taxable' => 'boolean',
        'billable' => 'boolean',
        'warranty_eligible' => 'boolean',
        'inactive' => 'boolean',

        'attributes' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Work order line items using this service
     */
    public function workOrderItems()
    {
        return $this->hasMany(
            \App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem::class
        );
    }

    /**
     * Subsidiary this service item belongs to (null = global)
     */
    public function subsidiary()
    {
        return $this->belongsTo(
            \App\Domain\Subsidiary\Models\Subsidiary::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Is this service available for selection?
     */
    public function isActive(): bool
    {
        return ! $this->inactive;
    }

    /**
     * Default pricing payload when added to a work order
     */
    public function toWorkOrderDefaults(): array
    {
        return [
            'display_name'    => $this->display_name,
            'description'     => $this->description,
            'billing_type'    => $this->billing_type,
            'unit_price'      => $this->default_rate ?? 0,
            'unit_cost'       => $this->default_cost,
            'taxable'         => $this->taxable,
            'billable'        => $this->billable,
            'warranty'        => $this->warranty_eligible,
        ];
    }
}
