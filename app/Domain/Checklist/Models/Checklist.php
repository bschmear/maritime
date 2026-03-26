<?php

namespace App\Domain\Checklist\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\ChecklistTemplate\Models\ChecklistTemplate;

class Checklist extends Model
{
    protected $table = 'checklists';

    protected $fillable = [
        'checklist_template_id',
        'name',
        'checklistable_id',
        'checklistable_type',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)
            ->orderBy('position');
    }

    public function checklistable(): MorphTo
    {
        return $this->morphTo();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isComplete(): bool
    {
        return $this->items()->where('required', true)->where('completed', false)->count() === 0;
    }

    public function progress(): array
    {
        $total = $this->items()->count();
        $completed = $this->items()->where('completed', true)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    public function scopeFor($query, $model)
    {
        return $query
            ->where('checklistable_id', $model->id)
            ->where('checklistable_type', get_class($model));
    }
}