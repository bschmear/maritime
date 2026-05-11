<?php

declare(strict_types=1);

namespace App\Domain\Opportunity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityFeatureRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'include_addons' => 'boolean',
        'asset_option_selections' => 'array',
        'addon_selections' => 'array',
        'addon_staff_decisions' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
