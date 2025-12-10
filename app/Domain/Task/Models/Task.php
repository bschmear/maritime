<?php

namespace App\Domain\Task\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'display_name',
        'notes',
        'start_date',
        'due_date',
        'completed_at',
        'status_id',
        'priority_id',
        'assigned_id',
        'created_by',
        'updated_by',
        'completed',
        'relatable_type',
        'relatable_id',
        'event_id',
        'reminder_at',
        'snoozed_until',
        'task_type_id',
        'recurring_rule',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'reminder_at' => 'datetime',
        'snoozed_until' => 'datetime',
        'completed' => 'boolean',
    ];

    /**
     * User assigned to this task.
     */
    public function assigned(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'assigned_id');
    }

    /**
     * User who created this task.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'created_by');
    }

    /**
     * User who last updated this task.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'updated_by');
    }

    /**
     * Get the related model (polymorphic).
     */
    public function relatable()
    {
        return $this->morphTo();
    }
}
