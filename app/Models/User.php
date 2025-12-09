<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable;

    /**
     * The database connection name for the model.
     * Users are stored in the central/public schema, not tenant schemas.
     *
     * @var string|null
     */
    protected $connection = 'pgsql';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'trial_ends_at',
        'current_tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function kioskRoles(): BelongsToMany
    {
        return $this->belongsToMany(KioskRole::class, 'kiosk_user');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function hasKioskRole(string $roleSlug): bool
    {
        return $this->kioskRoles()->where('slug', $roleSlug)->exists();
    }

    public function isKioskAdmin(): bool
    {
        return $this->hasKioskRole('admin');
    }

    /**
     * The user's current tenant.
     */
    public function currentTenant()
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }


    /**
     * Accounts this user belongs to.
     */
    public function accounts()
    {
        return $this->belongsToMany(Account::class)
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * Accounts where user is owner.
     */
    public function ownedAccounts()
    {
        return $this->hasMany(Account::class, 'owner_id');
    }

    /**
     * Check if user has a role in an account
     */
    public function hasRole(Account $account, $role)
    {
        $pivot = $this->accounts()->where('account_id', $account->id)->first()?->pivot;

        return $pivot && $pivot->role === $role;
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return trim($this->first_name . ' ' . $this->last_name);
        }

        return $this->name ?? '';
    }

    /**
     * Set the name attribute (for backward compatibility).
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;

        // If we're setting name and don't have first/last, try to split it
        if (!$this->first_name && !$this->last_name && $value) {
            $parts = explode(' ', $value, 2);
            $this->attributes['first_name'] = $parts[0] ?? '';
            $this->attributes['last_name'] = $parts[1] ?? '';
        }
    }

}
