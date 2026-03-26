<?php

namespace App\Domain\AssetSpec\Models;

use Illuminate\Database\Eloquent\Model;

class AssetSpecValue extends Model
{
    protected $fillable = [
        'asset_id',
        'asset_spec_definition_id',
        'value_number',
        'value_text',
        'value_boolean',
        'unit',
    ];

    public function definition()
    {
        return $this->belongsTo(AssetSpecDefinition::class, 'asset_spec_definition_id');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function getValueAttribute()
    {
        return $this->value_number
            ?? $this->value_text
            ?? $this->value_boolean;
    }
}
