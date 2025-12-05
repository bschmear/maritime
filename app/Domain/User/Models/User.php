<?php

namespace Domain\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Model
{
    protected $fillable = [
        'display_name',
        'first_name',
        'last_name',
        'email',
        'bio',
        'avatar',
        'current_role',
    ];

    /**
     * The role this user currently has.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(\Domain\Role\Models\Role::class, 'current_role');
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
}
