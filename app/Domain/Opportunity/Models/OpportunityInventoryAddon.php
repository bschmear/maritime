<?php

declare(strict_types=1);

namespace App\Domain\Opportunity\Models;

use App\Domain\AddOn\Models\AddOn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityInventoryAddon extends Model
{
    protected $fillable = [
        'inventory_item_opportunity_id',
        'addon_id',
        'name',
        'price',
        'quantity',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'metadata' => 'array',
    ];

    public function addon(): BelongsTo
    {
        return $this->belongsTo(AddOn::class, 'addon_id');
    }
}
