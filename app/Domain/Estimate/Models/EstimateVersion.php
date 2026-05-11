<?php

namespace App\Domain\Estimate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class EstimateVersion extends Model
{
    protected $table = 'estimate_versions';

    protected $guarded = ['id'];

    protected $casts = [
        'tax_rate' => 'decimal:3',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'is_primary' => 'boolean',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(EstimateVersion::class, 'copied_from_version_id');
    }

    public function lineItems(): MorphMany
    {
        return $this->morphMany(EstimateLineItem::class, 'parent')->orderBy('position')->orderBy('id');
    }

    public function line_items(): MorphMany
    {
        return $this->lineItems();
    }
}
