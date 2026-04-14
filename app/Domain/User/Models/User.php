<?php

namespace App\Domain\User\Models;

use App\Domain\Notification\Models\Notification;
use App\Domain\Role\Models\Role;
use App\Domain\Task\Models\Task;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'is_technician',
    ];

    protected $with = ['role'];

    protected $casts = [
        'is_technician' => 'boolean',
    ];

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
        return trim($this->first_name.' '.$this->last_name);
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

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\Location\Models\Location::class,
            'location_user'
        )->withTimestamps();
    }

    /**
     * Get the notifications assigned to this user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'assigned_to_user_id');
    }
}
