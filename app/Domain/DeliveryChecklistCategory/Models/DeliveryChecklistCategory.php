<?php

namespace App\Domain\DeliveryChecklistCategory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryChecklistCategory extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryChecklistTemplateItem::class, 'category_id');
    }

    public function deliveryChecklistItems(): HasMany
    {
        return $this->hasMany(\App\Domain\DeliveryChecklistItem\Models\DeliveryChecklistItem::class, 'category_id');
    }
}