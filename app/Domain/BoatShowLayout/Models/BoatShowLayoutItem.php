<?php

namespace App\Domain\BoatShowLayout\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatShowLayoutItem extends Model
{
    protected $table = 'boat_show_layout_items';

    protected $fillable = [
        'layout_id',
        'asset_unit_id',
        'inventory_unit_id',
        'name',
        'length_ft',
        'width_ft',
        'x',
        'y',
        'rotation',
        'color',
        'z_index',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(BoatShowLayout::class, 'layout_id');
    }
}
