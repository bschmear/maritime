<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Models;

use App\Enums\WarrantyClaim\LineItemCostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaimLineItem extends Model
{
    protected $table = 'warranty_claim_line_items';

    protected $fillable = [
        'warranty_claim_id',
        'work_order_service_item_id',
        'description',
        'cost_type',
        'quantity',
        'cost',
        'notes',
    ];

    protected $casts = [
        'cost_type' => LineItemCostType::class,
        'quantity' => 'integer',
        'cost' => 'decimal:2',
    ];

    protected $appends = [
        'line_total_cost',
    ];

    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class, 'warranty_claim_id');
    }

    public function workOrderServiceItem(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem::class, 'work_order_service_item_id');
    }

    public function getLineTotalCostAttribute(): float
    {
        $type = $this->cost_type ?? LineItemCostType::Quantity;

        return $type->lineTotal((int) $this->quantity, (float) $this->cost);
    }
}
