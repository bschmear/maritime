<?php

namespace App\Domain\BoatShowEvent\Models;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetUnit\Models\AssetUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatShowEventAsset extends Model
{
    protected $table = 'boat_show_event_assets';

    protected $fillable = [
        'boat_show_event_id',
        'asset_id',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(BoatShowEvent::class, 'boat_show_event_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class);
    }
}
