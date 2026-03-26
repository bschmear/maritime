<?php

namespace App\Domain\AssetSpec\Models;

use Illuminate\Database\Eloquent\Model;

class AssetSpecDefinition extends Model
{
    protected $fillable = [
        'key',
        'label',
        'group_id',
        'type',
        'unit',          // default/display unit
        'unit_imperial', // imperial unit
        'unit_metric',   // metric unit
        'use_metric',    // toggle for metric display
        'options',       // select options
        'is_filterable', // for marketplace filters
        'is_visible',    // UI display
        'is_required',   // required field
        'position',      // sort/order
        'asset_types',   // array of asset type IDs
    ];

    protected $casts = [
        'options' => 'array',
        'is_filterable' => 'boolean',
        'is_visible' => 'boolean',
        'is_required' => 'boolean',
        'use_metric' => 'boolean',
        'asset_types' => 'array',
    ];
    public function group()
    {
        return $this->belongsTo(SpecGroup::class, 'group_id');
    }
}
