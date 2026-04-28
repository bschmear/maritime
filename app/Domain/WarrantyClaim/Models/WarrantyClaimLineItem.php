<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaimLineItem extends Model
{
    protected $table = 'warranty_claim_line_items';

    protected $fillable = [
        'warranty_claim_id',
        'work_order_service_item_id',
        'description',
        'quantity',
        'price',
        'cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
    ];

    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class, 'warranty_claim_id');
    }

    public function workOrderServiceItem(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem::class, 'work_order_service_item_id');
    }
}
