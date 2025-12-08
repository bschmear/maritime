<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    protected $fillable = [
        'account_id',
        'user_id',
        'role',
        'email',
        'token',
    ];

    protected $casts = [
        'id' => 'string',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    /**
     * The account that sent the invitation.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The user who sent the invitation (account owner/admin).
     */
    public function inviter()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if invitation is still pending.
     */
    public function isPending(): bool
    {
        return is_null($this->accepted_at) && is_null($this->declined_at);
    }

    /**
     * Check if invitation was accepted.
     */
    public function isAccepted(): bool
    {
        return !is_null($this->accepted_at);
    }

    /**
     * Check if invitation was declined.
     */
    public function isDeclined(): bool
    {
        return !is_null($this->declined_at);
    }

    /**
     * Accept the invitation.
     */
    public function accept(User $user): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        // Add user to account
        $this->account->users()->attach($user->id, [
            'role' => $this->role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update invitation
        $this->update([
            'accepted_at' => now(),
            'user_id' => $user->id,
        ]);

        return true;
    }

    /**
     * Decline the invitation.
     */
    public function decline(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->update(['declined_at' => now()]);
        return true;
    }

    /**
     * Generate invitation URL.
     */
    public function getInvitationUrl(): string
    {
        return route('invitations.show', ['token' => $this->token]);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            // Generate UUID for primary key if not set
            if (empty($invitation->id)) {
                $invitation->id = (string) Str::uuid();
            }

            // Generate token if not set
            if (empty($invitation->token)) {
                $invitation->token = Str::random(40);
            }

            // If a user with this email exists, link them
            if (empty($invitation->user_id)) {
                $existingUser = \App\Models\User::where('email', $invitation->email)->first();
                if ($existingUser) {
                    $invitation->user_id = $existingUser->id;
                }
            }
        });
    }
}