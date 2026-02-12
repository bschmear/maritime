<?php

namespace App\Services;

use App\Domain\Location\Models\Location;
use \Stripe\StripeClient;
use Carbon\Carbon;

class TaxRateService
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('cashier.secret'));
    }

    /**
     * Get tax rate for a location, fetching from Stripe if needed.
     *
     * @param Location $location
     * @return float|null Returns tax rate as a decimal (e.g., 0.07 for 7%)
     */
    public function getTaxRate(Location $location): ?float
    {
        // If cached rate is less than 24 hours old, return it
        if ($location->tax_rate && $location->tax_last_fetched_at) {
            $lastFetched = Carbon::parse($location->tax_last_fetched_at);
            if ($lastFetched->gt(now()->subDay())) {
                return $location->tax_rate;
            }
        }

        // Fetch new tax rate from Stripe
        return $this->fetchTaxRateFromStripe($location);
    }

    /**
     * Fetch tax rate from Stripe Tax API.
     *
     * @param Location $location
     * @return float|null
     */
    protected function fetchTaxRateFromStripe(Location $location): ?float
    {
        try {
            // TODO: Implement Stripe Tax API integration
            // For now, return a placeholder tax rate based on state
            $stateTaxRates = [
                'FL' => 0.07, // Florida 7%
                'CA' => 0.085, // California 8.5%
                'TX' => 0.0625, // Texas 6.25%
                'NY' => 0.04, // New York 4%
                // Add more states as needed
            ];

            $state = strtoupper($location->state ?? '');
            $decimalRate = $stateTaxRates[$state] ?? 0.08; // Default 8% if state not found

            // Update location with new tax rate and set expiration to next midnight
            $location->tax_rate = $decimalRate;
            $location->tax_last_fetched_at = now()->startOfDay()->addDay(); // Next midnight
            $location->save();

            return $decimalRate;
        } catch (\Exception $e) {
            logger()->error('Failed to fetch Stripe tax rate for location ' . $location->id . ': ' . $e->getMessage());
            return $location->tax_rate; // fallback to last known
        }
    }
}
