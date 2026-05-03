<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetOption extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'input_type',
        'is_required',
        'allow_multiple',
        'min_select',
        'max_select',
        'active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'allow_multiple' => 'boolean',
        'active' => 'boolean',
        'min_select' => 'integer',
        'max_select' => 'integer',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(AssetOptionValue::class, 'option_id')
            ->where('active', true)
            ->orderBy('sort_order');
    }

    /**
     * All values including inactive (for admin).
     */
    public function allValues(): HasMany
    {
        return $this->hasMany(AssetOptionValue::class, 'option_id')
            ->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetOptionAssignment::class, 'option_id');
    }

    public function makeAssignments(): HasMany
    {
        return $this->hasMany(AssetOptionMakeAssignment::class, 'option_id');
    }
}
