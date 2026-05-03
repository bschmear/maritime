<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetOptionValue extends Model
{
    protected $fillable = [
        'option_id',
        'label',
        'value',
        'color_hex',
        'cost',
        'price',
        'sort_order',
        'is_default',
        'active',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'price' => 'decimal:2',
        'active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(AssetOption::class, 'option_id');
    }
}
