<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Tenant;

class Account extends Model
{
    use HasFactory;

    /**
     * The database connection name for the model.
     * Accounts are stored in the central/public schema, not tenant schemas.
     *
     * @var string|null
     */
    protected $connection = 'pgsql';

    protected $fillable = [
        'name',
        'owner_id',
        'tenant_id',
    ];

    /**
     * The owner of the account.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The tenant associated with this account.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Domains for this account's tenant.
     */
    public function domains()
    {
        return $this->hasMany(\Stancl\Tenancy\Database\Models\Domain::class, 'tenant_id', 'tenant_id');
    }

    /**
     * Users belonging to this account.
     */
    public function users()
    {
        return $this->belongsToMany(User::class)
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * Get users with a specific role.
     */
    public function admins()
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    public function members()
    {
        return $this->users()->wherePivot('role', 'member');
    }

    /**
     * Get all subscriptions for this account.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the current active subscription for this account.
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Get the current plan for this account.
     */
    public function currentPlan()
    {
        return $this->subscription?->plan;
    }

    /**
     * Check if account has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        $subscription = $this->subscription;
        return $subscription && $subscription->isActive();
    }

    /**
     * Check if account is within included seat limit (for display/marketing).
     */
    public function withinSeatLimit(): bool
    {
        $plan = $this->currentPlan();
        if (!$plan) return false;

        return $this->users->count() <= $plan->seat_limit;
    }

    /**
     * Get number of seats over the included limit.
     */
    public function seatsOverLimit(): int
    {
        $plan = $this->currentPlan();
        if (!$plan) return $this->users->count();

        return max(0, $this->users->count() - $plan->seat_limit);
    }

    /**
     * Calculate additional cost for over-limit seats (using global extra seat pricing).
     */
    public function additionalSeatCost(): float
    {
        $extraSeats = $this->seatsOverLimit();
        if ($extraSeats <= 0) return 0;

        // Get global extra seat pricing from config
        $extraSeatConfig = config('app.extra_seats');
        return $extraSeats * ($extraSeatConfig['monthly_price'] ?? 15.00);
    }

    /**
     * Calculate additional yearly cost for over-limit seats.
     */
    public function additionalYearlySeatCost(): float
    {
        $extraSeats = $this->seatsOverLimit();
        if ($extraSeats <= 0) return 0;

        // Get global extra seat pricing from config
        $extraSeatConfig = config('app.extra_seats');
        return $extraSeats * ($extraSeatConfig['yearly_price'] ?? 150.00);
    }

    /**
     * Get total monthly cost breakdown.
     */
    public function getCostBreakdown(): array
    {
        $plan = $this->currentPlan();
        if (!$plan) return ['base' => 0, 'extra' => 0, 'total' => 0];

        $extraSeats = $this->seatsOverLimit();
        $extraCost = $extraSeats * ($plan->seat_extra ?? 0);

        return [
            'base' => $plan->monthly_price ?? 0,
            'extra_seats' => $extraSeats,
            'extra_cost' => $extraCost,
            'total' => ($plan->monthly_price ?? 0) + $extraCost,
        ];
    }

    /**
     * Pending invitations for this account.
     */
    public function pendingInvitations()
    {
        return $this->hasMany(Invitation::class)->whereNull('accepted_at')->whereNull('declined_at');
    }

    /**
     * All invitations for this account.
     */
    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }
}
