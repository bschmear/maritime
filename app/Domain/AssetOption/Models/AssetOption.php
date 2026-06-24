<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetOption extends Model
{
    protected $appends = ['display_name'];

    protected $fillable = [
        'name',
        'slug',
        'category_id',
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
        'category_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetOptionCategory::class, 'category_id');
    }

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

    /**
     * Toggle options use a single implicit "on" value for pricing and persistence.
     */
    public function ensureToggleOnValue(): AssetOptionValue
    {
        $existing = $this->allValues()->where('value', 'on')->first()
            ?? $this->allValues()->orderBy('sort_order')->first();

        if ($existing !== null) {
            if (! $existing->active) {
                $existing->update(['active' => true]);
            }

            return $existing->fresh();
        }

        return $this->allValues()->create([
            'label' => 'On',
            'value' => 'on',
            'sort_order' => 0,
            'is_default' => true,
            'active' => true,
        ]);
    }

    /**
     * Used by records.lookup and pickers (there is no display_name column).
     */
    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->attributes['name'] ?? '');
    }
}
