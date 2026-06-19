<?php

declare(strict_types=1);

namespace App\Domain\Location\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocationLayout extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'location_id',
        'name',
        'width_ft',
        'height_ft',
        'grid_size',
        'scale',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function layoutUnits(): HasMany
    {
        return $this->hasMany(LocationLayoutUnit::class);
    }
}
