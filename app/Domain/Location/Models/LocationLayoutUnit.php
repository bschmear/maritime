<?php

declare(strict_types=1);

namespace App\Domain\Location\Models;

use App\Domain\AssetUnit\Models\AssetUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationLayoutUnit extends Model
{
    protected $fillable = [
        'location_layout_id',
        'asset_unit_id',
        'include_in_layout',
        'x',
        'y',
        'rotation',
        'z_index',
        'name',
        'length_ft',
        'width_ft',
        'color',
    ];

    protected $casts = [
        'include_in_layout' => 'boolean',
        'x' => 'float',
        'y' => 'float',
        'rotation' => 'integer',
        'z_index' => 'integer',
        'length_ft' => 'float',
        'width_ft' => 'float',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(LocationLayout::class, 'location_layout_id');
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class);
    }
}
