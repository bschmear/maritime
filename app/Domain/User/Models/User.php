<?php

namespace App\Domain\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Task\Models\Task;
use App\Domain\Role\Models\Role;
use App\Models\Concerns\HasDocuments;

class User extends Model
{
    use HasDocuments;

    protected $fillable = [
        'display_name',
        'first_name',
        'last_name',
        'email',
        'bio',
        'avatar',
        'office_phone',
        'mobile_phone',
        'current_role',
    ];

    protected $with = ['role'];

    /**
     * The role this user currently has.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'current_role');
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get the user's display name or fall back to full name.
     */
    public function getDisplayNameOrFullNameAttribute(): string
    {
        return $this->display_name ?: $this->full_name;
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'relatable');
    }

    public function subsidiaries()
    {
        return $this->belongsToMany(
            \App\Domain\Subsidiary\Models\Subsidiary::class,
            'subsidiary_user'
        )->withPivot(['primary'])
        ->withTimestamps();
    }

}
