<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Models;

use App\Domain\Estimate\Models\EstimateLineItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends TransactionLineItem
{
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'parent_id');
    }

    public function estimateLineItem(): BelongsTo
    {
        return $this->belongsTo(EstimateLineItem::class, 'source_transaction_line_item_id');
    }
}
