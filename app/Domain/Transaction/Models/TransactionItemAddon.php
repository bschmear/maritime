<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItemAddon extends TransactionLineItemAddon
{
    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'transaction_line_item_id');
    }
}
