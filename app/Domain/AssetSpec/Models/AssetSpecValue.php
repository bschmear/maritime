<?php

namespace App\Domain\AssetSpec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AssetSpecValue extends Model
{
    protected $fillable = [
        'specable_type',
        'specable_id',
        'asset_spec_definition_id',
        'value_number',
        'value_text',
        'value_boolean',
        'unit',
    ];

    protected $casts = [
        'value_number' => 'float',
    ];

    public function definition()
    {
        return $this->belongsTo(AssetSpecDefinition::class, 'asset_spec_definition_id');
    }

    public function specable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getValueAttribute()
    {
        return $this->value_number
            ?? $this->value_text
            ?? $this->value_boolean;
    }
}
