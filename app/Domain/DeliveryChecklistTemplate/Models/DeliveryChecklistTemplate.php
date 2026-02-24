<?php

namespace App\Domain\DeliveryChecklistTemplate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryChecklistTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];


    public function items(): HasMany
    {
        return $this->hasMany(DeliveryChecklistTemplateItem::class)->orderBy('sort_order');
    }
}