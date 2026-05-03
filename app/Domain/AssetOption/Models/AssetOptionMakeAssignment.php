<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Models;

use App\Domain\BoatMake\Models\BoatMake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetOptionMakeAssignment extends Model
{
    protected $fillable = [
        'option_id',
        'make_id',
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

    public function make(): BelongsTo
    {
        return $this->belongsTo(BoatMake::class, 'make_id');
    }
}
