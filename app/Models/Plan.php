<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'monthly_price',
        'yearly_price',
        'stripe_monthly_id',
        'stripe_yearly_id',
        'seat_limit',
        'description',
        'included',
        'active',
        'popular',
    ];

    protected $casts = [
        'included' => 'array',
        'active' => 'boolean',
        'popular' => 'boolean',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
    ];

    /**
     * Get the Stripe price ID based on billing cycle
     */
    public function getStripePriceId(string $billingCycle): ?string
    {
        return $billingCycle === 'yearly' 
            ? $this->stripe_yearly_id 
            : $this->stripe_monthly_id;
    }

    /**
     * Get the price based on billing cycle
     */
    public function getPrice(string $billingCycle): float
    {
        return $billingCycle === 'yearly' 
            ? (float) $this->yearly_price 
            : (float) $this->monthly_price;
    }

    /**
     * Scope to get only active plans
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Plan items (add-ons)
     */
    public function items()
    {
        return $this->hasMany(PlanItem::class);
    }
}
