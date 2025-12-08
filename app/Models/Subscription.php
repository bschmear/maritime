<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'plan_id',
        'billing_cycle',
        'type',
        'stripe_id',
        'stripe_status',
        'stripe_price',
        'trial_ends_at',
        'ends_at',
        'quantity',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * The user that owns the subscription.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * The account that owns the subscription.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The plan for this subscription.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->stripe_status === 'active' ||
               ($this->stripe_status === 'trialing' && $this->trial_ends_at->isFuture());
    }

    /**
     * Check if subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->stripe_status === 'trialing' && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription is cancelled.
     */
    public function cancelled(): bool
    {
        return !is_null($this->ends_at);
    }
}