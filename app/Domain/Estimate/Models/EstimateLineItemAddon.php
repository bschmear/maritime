<?php

declare(strict_types=1);

namespace App\Domain\Estimate\Models;

use App\Domain\Transaction\Models\TransactionLineItemAddon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimateLineItemAddon extends TransactionLineItemAddon
{
    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(EstimateLineItem::class, 'transaction_line_item_id');
    }
}
