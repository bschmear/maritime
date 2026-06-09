<?php

namespace App\Domain\Checklist\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $table = 'checklist_items';

    protected $fillable = [
        'checklist_id',
        'label',
        'required',
        'completed',
        'completed_at',
        'position',
        'completed_by',
        'response',
        'manager_approved',
        'manager_approved_at',
        'manager_approved_by',
    ];

    protected $casts = [
        'required' => 'boolean',
        'completed' => 'boolean',
        'completed_at' => 'datetime',
        'manager_approved' => 'boolean',
        'manager_approved_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function markComplete(?int $userId = null): void
    {
        $this->update([
            'completed' => true,
            'completed_at' => now(),
            'completed_by' => $userId,
        ]);
    }

    public function markIncomplete(): void
    {
        $this->update([
            'completed' => false,
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }
}
