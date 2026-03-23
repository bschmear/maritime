<?php

namespace App\Domain\Transaction\Models;

use App\Domain\Estimate\Models\EstimateLineItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TransactionItem extends Model
{
    protected $table = 'transaction_items';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'taxable' => 'boolean',
        'tax_rate' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function estimateLineItem(): BelongsTo
    {
        return $this->belongsTo(EstimateLineItem::class, 'estimate_item_id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(TransactionItemAddon::class, 'transaction_item_id');
    }
}
