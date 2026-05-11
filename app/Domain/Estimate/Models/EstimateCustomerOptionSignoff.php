<?php

declare(strict_types=1);

namespace App\Domain\Estimate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimateCustomerOptionSignoff extends Model
{
    protected $table = 'estimate_customer_option_signoffs';

    protected $guarded = ['id'];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(EstimateLineItem::class, 'transaction_line_item_id');
    }
}
