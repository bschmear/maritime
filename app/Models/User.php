<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription as CashierSubscription;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable;

    /**
     * @var array<string, CashierSubscription|null>
     */
    private static array $cashierSubscriptionByAccount = [];

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
        'google_id',
        'password',
        'trial_ends_at',
        'current_tenant_id',
        'is_support',
        'admin_access',
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
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'display_name',
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
            'is_support' => 'boolean',
            'admin_access' => 'boolean',
        ];
    }

    public function hasAdminAccess(): bool
    {
        return (bool) $this->admin_access;
    }

    public function isSupportStaff(): bool
    {
        return (bool) $this->is_support;
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
     * The owner's Laravel Cashier subscription row for a given workspace account.
     * Uses subscriptions.account_id so multiple workspaces per owner each have their own Stripe subscription.
     */
    public function cashierSubscriptionForAccount(Account $account): ?CashierSubscription
    {
        $cacheKey = $this->id.'|'.$account->id;

        if (array_key_exists($cacheKey, self::$cashierSubscriptionByAccount)) {
            return self::$cashierSubscriptionByAccount[$cacheKey];
        }

        if ($this->relationLoaded('subscriptions')) {
            $subscription = $this->subscriptions
                ->where('account_id', $account->id)
                ->sortByDesc('id')
                ->first();
        } else {
            $subscription = $this->subscriptions()
                ->where('account_id', $account->id)
                ->latest('id')
                ->first();
        }

        return self::$cashierSubscriptionByAccount[$cacheKey] = $subscription;
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
            return trim($this->first_name.' '.$this->last_name);
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
        if (! $this->first_name && ! $this->last_name && $value) {
            $parts = explode(' ', $value, 2);
            $this->attributes['first_name'] = $parts[0] ?? '';
            $this->attributes['last_name'] = $parts[1] ?? '';
        }
    }

    /**
     * Get the display name for this user.
     * Used by the RecordController when loading relationships.
     */
    public function getDisplayNameAttribute(): string
    {
        // Return full name if available, otherwise fall back to name/email
        if ($this->first_name || $this->last_name) {
            return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
        }

        return $this->name ?: $this->email;
    }
}
