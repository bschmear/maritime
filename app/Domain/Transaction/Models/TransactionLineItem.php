<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Models;

use App\Domain\AssetOption\Models\EstimateSelectedOption;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\Estimate\Models\EstimateCustomerOptionSignoff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TransactionLineItem extends Model
{
    protected $table = 'transaction_line_items';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'taxable' => 'boolean',
        'tax_rate' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'position' => 'integer',
        'customer_asset_options_completed_at' => 'datetime',
    ];

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sourceLine(): BelongsTo
    {
        return $this->belongsTo(static::class, 'source_transaction_line_item_id');
    }

    public function assetVariant(): BelongsTo
    {
        return $this->belongsTo(AssetVariant::class, 'asset_variant_id');
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(TransactionLineItemAddon::class, 'transaction_line_item_id');
    }

    /** Boat / asset option premiums stored on `transaction_line_item_selected_options`. */
    public function selectedAssetOptions(): HasMany
    {
        return $this->hasMany(EstimateSelectedOption::class, 'transaction_line_item_id');
    }

    /**
     * When the deal line was copied from an estimate, option rows still FK the estimate line id.
     * Deal rows use {@see source_transaction_line_item_id} to point at that line.
     */
    public function selectedAssetOptionsFromSourceLine(): HasMany
    {
        return $this->hasMany(EstimateSelectedOption::class, 'transaction_line_item_id', 'source_transaction_line_item_id');
    }

    public function customerOptionSignoffs(): HasMany
    {
        return $this->hasMany(EstimateCustomerOptionSignoff::class, 'transaction_line_item_id');
    }
}
