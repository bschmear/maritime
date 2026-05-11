<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Models;

use App\Domain\Estimate\Models\Estimate;
use App\Domain\Estimate\Models\EstimateLineItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimateSelectedOption extends Model
{
    protected $table = 'transaction_line_item_selected_options';

    protected $fillable = [
        'estimate_id',
        'transaction_line_item_id',
        'option_id',
        'option_value_id',
        'option_name',
        'value_label',
        'cost',
        'price',
        'taxable',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'price' => 'decimal:2',
        'taxable' => 'boolean',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(AssetOption::class, 'option_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(AssetOptionValue::class, 'option_value_id');
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(EstimateLineItem::class, 'transaction_line_item_id');
    }
}
