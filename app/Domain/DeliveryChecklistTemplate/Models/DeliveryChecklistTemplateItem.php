<?php

namespace App\Domain\DeliveryChecklistTemplate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryChecklistTemplateItem extends Model
{

    protected $fillable = [
        'delivery_checklist_template_id',
        'label',
        'category_id',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DeliveryChecklistTemplate::class, 'delivery_checklist_template_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\DeliveryChecklistCategory\Models\DeliveryChecklistCategory::class, 'category_id');
    }
}