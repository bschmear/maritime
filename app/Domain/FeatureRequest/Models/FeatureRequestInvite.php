<?php

declare(strict_types=1);

namespace App\Domain\FeatureRequest\Models;

use App\Domain\Estimate\Models\Estimate;
use App\Domain\Opportunity\Models\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureRequestInvite extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'include_addons' => 'boolean',
        'addon_catalog_ids' => 'array',
        'metadata' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }
}
