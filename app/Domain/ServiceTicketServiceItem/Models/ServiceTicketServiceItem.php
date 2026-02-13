<?php

namespace App\Domain\ServiceTicketServiceItem\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceTicketServiceItem extends Model
{
    protected $table = 'service_ticket_service_items';

    protected $fillable = [
        'service_ticket_id', 'service_item_id', 'display_name', 'description',
        'quantity', 'unit_price', 'unit_cost', 'total_price', 'total_cost',
        'estimated_hours', 'actual_hours', 'billable', 'warranty',
        'inactive', 'sort_order', 'attributes', 'billing_type',
    ];

    protected $casts = [
        'billing_type' => 'integer',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_price' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'billable' => 'boolean',
        'warranty' => 'boolean',
        'inactive' => 'boolean',
        'attributes' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function serviceTicket(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\ServiceTicket\Models\ServiceTicket::class);
    }

    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\ServiceItem\Models\ServiceItem::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Computed
    |--------------------------------------------------------------------------
    */

    public function recalculate(): void
    {
        $quantity = (float) ($this->quantity ?? 1);
        $unitPrice = (float) ($this->unit_price ?? 0);
        $unitCost = (float) ($this->unit_cost ?? 0);
        $estimatedHours = (float) ($this->estimated_hours ?? 0);
        $actualHours = (float) ($this->actual_hours ?? 0);

        $totalPrice = 0;
        $totalCost = 0;

        switch ($this->billing_type) {
            case 1: // Hourly
                $totalPrice = $estimatedHours * $unitPrice;
                $totalCost = $actualHours * $unitCost;
                break;
            case 2: // Flat
                $totalPrice = $unitPrice;
                $totalCost = $unitCost;
                break;
            case 3: // Quantity
            default:
                $totalPrice = $quantity * $unitPrice;
                $totalCost = $quantity * $unitCost;
                break;
        }

        if ($this->warranty) {
            $totalPrice = 0;
        }

        $this->update([
            'total_price' => $totalPrice,
            'total_cost' => $totalCost,
        ]);
    }
}