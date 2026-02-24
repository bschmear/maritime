<?php

namespace App\Domain\DeliveryChecklistItem\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryChecklistItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'delivery_id',
        'template_item_id',
        'label',
        'category_id',
        'is_required',
        'completed',
        'notes',
        'photo_path',
        'completed_at',
        'completed_by',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'completed' => 'boolean',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Delivery\Models\Delivery::class);
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\DeliveryChecklistTemplate\Models\DeliveryChecklistTemplateItem::class, 'template_item_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'completed_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\DeliveryChecklistCategory\Models\DeliveryChecklistCategory::class, 'category_id');
    }
}