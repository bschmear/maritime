<?php

namespace App\Domain\InvoiceItem\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetVariant\Models\AssetVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'transaction_item_id',

        'name',
        'description',

        'quantity',
        'unit_price',
        'discount',

        'subtotal',

        'taxable',
        'tax_rate',
        'tax_amount',

        'total',

        'position',

        'itemable_type',
        'itemable_id',
        'asset_variant_id',
        'asset_unit_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'taxable' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            \App\Domain\Invoice\Models\Invoice::class
        );
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assetVariant(): BelongsTo
    {
        return $this->belongsTo(AssetVariant::class, 'asset_variant_id');
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function calculateTotals(): void
    {
        $subtotal = ($this->quantity * $this->unit_price) - $this->discount;

        $taxAmount = 0;

        if ($this->taxable && $this->tax_rate) {
            $taxAmount = round($subtotal * ($this->tax_rate / 100), 2);
        }

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->total = $subtotal + $taxAmount;
    }

    /*
    |--------------------------------------------------------------------------
    | Model Events (auto-calc)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::saving(function ($item) {
            $item->calculateTotals();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors (UI helpers)
    |--------------------------------------------------------------------------
    */

    public function getFormattedUnitPriceAttribute(): string
    {
        return number_format($this->unit_price, 2);
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return number_format($this->subtotal, 2);
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total, 2);
    }
}
