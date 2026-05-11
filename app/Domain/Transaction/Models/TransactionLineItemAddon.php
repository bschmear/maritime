<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Models;

use App\Domain\AddOn\Models\AddOn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionLineItemAddon extends Model
{
    protected $table = 'transaction_line_item_addons';

    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'taxable' => 'boolean',
        'tax_rate' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'metadata' => 'json',
    ];

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(TransactionLineItem::class, 'transaction_line_item_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(AddOn::class, 'addon_id');
    }
}
