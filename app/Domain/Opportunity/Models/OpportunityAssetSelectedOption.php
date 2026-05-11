<?php

declare(strict_types=1);

namespace App\Domain\Opportunity\Models;

use App\Domain\AssetOption\Models\AssetOption;
use App\Domain\AssetOption\Models\AssetOptionValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityAssetSelectedOption extends Model
{
    protected $fillable = [
        'asset_opportunity_id',
        'option_id',
        'option_value_id',
        'option_name',
        'value_label',
        'cost',
        'price',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(AssetOption::class, 'option_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(AssetOptionValue::class, 'option_value_id');
    }
}
