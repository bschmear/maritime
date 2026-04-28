<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Models;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\WarrantyClaim\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarrantyClaim extends Model
{
    protected $table = 'warrantyclaims';

    protected $fillable = [
        'vendor_id',
        'work_order_id',
        'invoice_id',
        'claim_number',
        'status',
        'total_amount',
        'submitted_at',
        'approved_at',
        'paid_at',
        'voided_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'status' => Status::class,
        'total_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(WarrantyClaimLineItem::class)->orderBy('id');
    }
}
