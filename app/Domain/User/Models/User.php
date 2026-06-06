<?php

namespace App\Domain\User\Models;

use App\Domain\Location\Models\Location;
use App\Domain\Notification\Models\Notification;
use App\Domain\Role\Models\Role;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Task\Models\Task;
use App\Domain\UserFavorite\Models\UserFavorite;
use App\Enums\ServiceTicket\SignatureMethod;
use App\Models\Concerns\HasDocuments;
use App\Support\SignatureStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    use HasDocuments;

    protected $connection = 'tenant';

    protected $fillable = [
        'display_name',
        'first_name',
        'last_name',
        'position_title',
        'email',
        'bio',
        'avatar',
        'office_phone',
        'mobile_phone',
        'current_role',
        'is_technician',
        'delivery_in_progress',
        'signature_method',
        'signature_file',
        'typed_signature',
        'signature_saved_at',
    ];

    protected $with = ['role.permissions'];

    protected $casts = [
        'is_technician' => 'boolean',
        'delivery_in_progress' => 'boolean',
        'signature_method' => 'integer',
        'signature_saved_at' => 'datetime',
    ];

    /**
     * The role this user currently has.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'current_role');
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->role) {
            return false;
        }

        return $this->role->hasPermission($permission);
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
            Subsidiary::class,
            'subsidiary_user'
        )->withPivot(['primary'])
            ->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(
            Location::class,
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

    public function favorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class);
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return SignatureStorage::url($this->signature_file);
    }

    public function hasSavedSignature(): bool
    {
        if ($this->signature_method === SignatureMethod::DigitalTyped->value) {
            return filled($this->typed_signature);
        }

        return filled($this->signature_file);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function savedSignaturePayload(): ?array
    {
        if (! $this->hasSavedSignature()) {
            return null;
        }

        return [
            'method' => $this->signature_method === SignatureMethod::DigitalTyped->value ? 'type' : 'draw',
            'signature_method' => $this->signature_method,
            'signature_url' => $this->signature_url,
            'typed_signature' => $this->typed_signature,
            'signed_name' => $this->display_name ?: $this->full_name,
            'saved_at' => $this->signature_saved_at?->toIso8601String(),
        ];
    }
}
