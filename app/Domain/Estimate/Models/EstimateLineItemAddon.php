<?php

namespace App\Domain\Estimate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimateLineItemAddon extends Model
{
    protected $table = 'estimate_line_item_addon';

    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'metadata' => 'json',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(EstimateLineItem::class, 'estimate_line_item_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\AddOn\Models\AddOn::class);
    }
}
