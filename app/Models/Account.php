<?php

namespace App\Models;

use App\Services\WorkspacePlanCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Models\Domain;

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
        'allow_support_access',
    ];

    protected function casts(): array
    {
        return [
            'allow_support_access' => 'boolean',
        ];
    }

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
        return $this->hasMany(Domain::class, 'tenant_id', 'tenant_id');
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
        return $this->users()->wherePivotIn('role', ['manager', 'employee', 'guest']);
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
     *
     * Uses the account owner's Stripe (Cashier) subscription so the UI matches
     * what switch/cancel and Stripe actually use — not merely the latest local row.
     */
    public function currentPlan(): ?Plan
    {
        $cashier = $this->owner?->cashierSubscriptionForAccount($this);

        if (! $cashier || ! $cashier->active() || ! $cashier->plan_id) {
            return null;
        }

        return Plan::query()->find($cashier->plan_id);
    }

    /**
     * Check if account has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        $cashier = $this->owner?->cashierSubscriptionForAccount($this);

        return $cashier !== null && $cashier->active();
    }

    public function hasTicketSupportAccess(): bool
    {
        if (tenant()) {
            $cached = WorkspacePlanCache::get();
            if ($cached !== null && ($cached['account_id'] ?? null) === $this->id) {
                return (bool) ($cached['ticket_support_access'] ?? false);
            }
        }

        return (bool) $this->currentPlan()?->ticket_support_access;
    }

    /**
     * Check if account is within included seat limit (for display/marketing).
     */
    public function withinSeatLimit(): bool
    {
        $plan = $this->currentPlan();
        if (! $plan) {
            return false;
        }

        return $this->users->count() <= $plan->seat_limit;
    }

    /**
     * Get number of seats over the included limit.
     */
    public function seatsOverLimit(): int
    {
        $plan = $this->currentPlan();
        if (! $plan) {
            return $this->users()->count();
        }

        return max(0, $this->users()->count() - $plan->seat_limit);
    }

    /**
     * Calculate additional cost for over-limit seats (using global extra seat pricing).
     */
    public function additionalSeatCost(): float
    {
        $extraSeats = $this->seatsOverLimit();
        if ($extraSeats <= 0) {
            return 0;
        }

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
        if ($extraSeats <= 0) {
            return 0;
        }

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
        if (! $plan) {
            return ['base' => 0, 'extra' => 0, 'total' => 0];
        }

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
     * Seat counts for workspace billing UI (central account members vs plan included seats).
     *
     * @return array{current_users: int, seat_limit: int, available_seats: int, over_limit: int, additional_cost: float}
     */
    public function seatUsageForDisplay(): array
    {
        $this->loadMissing('users');
        $currentPlan = $this->currentPlan();
        $currentUsers = $this->users->count();
        $seatLimit = $currentPlan?->seat_limit ?? 1;

        return [
            'current_users' => $currentUsers,
            'seat_limit' => $seatLimit,
            'available_seats' => $this->withinSeatLimit() ? max(0, $seatLimit - $currentUsers) : 0,
            'over_limit' => $this->seatsOverLimit(),
            'additional_cost' => (float) $this->additionalSeatCost(),
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
