<?php

namespace App\Domain\Transaction\Models;

use App\Domain\AddOn\Models\AddOn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItemAddon extends Model
{
    protected $table = 'transaction_item_addon';

    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'taxable' => 'boolean',
        'tax_rate' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'metadata' => 'json',
    ];

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'transaction_item_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(AddOn::class, 'addon_id');
    }
}
