<?php

namespace App\Domain\AssetSpec\Models;

use Illuminate\Database\Eloquent\Model;

class SpecGroup extends Model
{
    protected $fillable = [
        'key',
        'name',
        'position',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function specDefinitions()
    {
        return $this->hasMany(AssetSpecDefinition::class, 'group_id');
    }
}