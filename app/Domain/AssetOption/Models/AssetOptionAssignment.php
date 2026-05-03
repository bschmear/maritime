<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Models;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetVariant\Models\AssetVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetOptionAssignment extends Model
{
    protected $fillable = [
        'option_id',
        'asset_id',
        'variant_id',
        'cost_override',
        'price_override',
        'active',
    ];

    protected $casts = [
        'cost_override' => 'decimal:2',
        'price_override' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(AssetOption::class, 'option_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(AssetVariant::class, 'variant_id');
    }
}
