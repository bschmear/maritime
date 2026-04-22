<?php

namespace App\Domain\ServiceTicketServiceItem\Models;

use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceTicketServiceItem extends Model
{
    protected $table = 'service_ticket_service_items';

    protected $fillable = [
        'service_ticket_id', 'service_item_id', 'display_name', 'description',
        'quantity', 'unit_price', 'unit_cost', 'total_price', 'total_cost',
        'estimated_hours', 'billable', 'warranty', 'warranty_type', 'billable_to',
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
        'billable' => 'boolean',
        'warranty' => 'boolean',
        'warranty_type' => WarrantyCoverageType::class,
        'billable_to' => 'string',
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
        $billableTo = $this->billable_to ?: $this->resolveBillableTo();

        $totalPrice = 0;
        $totalCost = 0;

        switch ($this->billing_type) {
            case 1: // Hourly
                $totalPrice = $estimatedHours * $unitPrice;
                $totalCost = $estimatedHours * $unitCost;
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

        if ($billableTo === 'internal') {
            $totalPrice = 0;
        }

        $this->update([
            'total_price' => $totalPrice,
            'total_cost' => $totalCost,
            'billable_to' => $billableTo,
        ]);
    }

    protected function resolveBillableTo(): string
    {
        if (! $this->warranty) {
            return 'customer';
        }

        $warrantyType = $this->warranty_type instanceof WarrantyCoverageType
            ? $this->warranty_type->value
            : $this->warranty_type;

        return $warrantyType === WarrantyCoverageType::Manufacturer->value
            ? 'manufacturer'
            : 'internal';
    }
}
