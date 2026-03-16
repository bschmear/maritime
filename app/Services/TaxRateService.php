<?php

namespace App\Services;

use App\Domain\Location\Models\Location;
use Carbon\Carbon;
use Stripe\StripeClient;

class TaxRateService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('cashier.secret'));
    }

    /**
     * Get tax rate for a saved Location model.
     * Result is cached on the model for 24 hours to avoid redundant Stripe calls.
     *
     * Returns the rate as a decimal (e.g. 0.07 for 7%).
     */
    public function getTaxRate(Location $location): ?float
    {
        if ($location->tax_rate && $location->tax_last_fetched_at) {
            if (Carbon::parse($location->tax_last_fetched_at)->gt(now()->subDay())) {
                return $location->tax_rate;
            }
        }

        $rate = $this->fetchFromStripe([
            'line1'       => $location->address_line1  ?? $location->address ?? '',
            'city'        => $location->city            ?? '',
            'state'       => $location->state           ?? '',
            'postal_code' => $location->postal_code     ?? '',
            'country'     => $location->country         ?? 'US',
        ]);

        if ($rate !== null) {
            $location->tax_rate             = $rate;
            $location->tax_last_fetched_at  = now()->startOfDay()->addDay();
            $location->save();
        }

        return $rate;
    }

    /**
     * Get tax rate from raw address fields (no Location model required).
     * Used by estimates and any other record with a billing address.
     *
     * Returns the rate as a percentage (e.g. 7.0 for 7%).
     */
    public function getTaxRateByAddress(
        string  $state,
        ?string $city        = null,
        ?string $postalCode  = null,
        ?string $country     = 'US',
        ?string $line1       = ''
    ): float {
        $rate = $this->fetchFromStripe([
            'line1'       => $line1       ?? '',
            'city'        => $city        ?? '',
            'state'       => $state,
            'postal_code' => $postalCode  ?? '',
            'country'     => $country     ?? 'US',
        ]);

        // Stripe returned a decimal — convert to percentage
        if ($rate !== null) {
            return round($rate * 100, 4);
        }

        // Fallback to base state rates if Stripe fails
        return $this->fallbackStateRate($state);
    }

    /**
     * Keep old name as a thin alias so nothing else breaks.
     */
    public function getTaxRateByState(string $state): float
    {
        return $this->getTaxRateByAddress($state);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Call Stripe Tax Calculations with a $100 dummy line item to derive the
     * effective tax rate for the given address.
     *
     * Returns the rate as a decimal (e.g. 0.0725 for 7.25%), or null on failure.
     */
    protected function fetchFromStripe(array $address): ?float
    {
        $address = array_filter($address, fn ($v) => $v !== null && $v !== '');

        if (empty($address['state']) && empty($address['postal_code'])) {
            return null;
        }

        try {
            $calculation = $this->stripe->tax->calculations->create([
                'currency'         => 'usd',
                'customer_details' => [
                    'address'        => array_merge(['country' => 'US'], $address),
                    'address_source' => 'billing',
                ],
                'line_items' => [
                    [
                        // $100 dummy item; any amount works — we only need the rate
                        'amount'       => 10000,
                        'reference'    => 'tax_rate_lookup',
                        'tax_behavior' => 'exclusive',
                        'tax_code'     => 'txcd_99999999', // General – Tangible Goods
                    ],
                ],
            ]);

            $taxCents  = $calculation->tax_amount_exclusive ?? 0;
            $baseCents = 10000;

            return $baseCents > 0 ? round($taxCents / $baseCents, 6) : null;
        } catch (\Exception $e) {
            logger()->error('Stripe Tax API error', [
                'message' => $e->getMessage(),
                'address' => $address,
            ]);

            return null;
        }
    }

    /**
     * Last-resort fallback used only when Stripe is unavailable.
     * Returns a percentage (e.g. 7.0).
     */
    protected function fallbackStateRate(string $state): float
    {
        $nameToCode = [
            'florida' => 'FL', 'california' => 'CA', 'texas' => 'TX', 'new york' => 'NY',
            'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
            'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE', 'georgia' => 'GA',
            'hawaii' => 'HI', 'idaho' => 'ID', 'illinois' => 'IL', 'indiana' => 'IN',
            'iowa' => 'IA', 'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA',
            'maine' => 'ME', 'maryland' => 'MD', 'massachusetts' => 'MA', 'michigan' => 'MI',
            'minnesota' => 'MN', 'mississippi' => 'MS', 'missouri' => 'MO', 'montana' => 'MT',
            'nebraska' => 'NE', 'nevada' => 'NV', 'new hampshire' => 'NH', 'new jersey' => 'NJ',
            'new mexico' => 'NM', 'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH',
            'oklahoma' => 'OK', 'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI',
            'south carolina' => 'SC', 'south dakota' => 'SD', 'tennessee' => 'TN', 'utah' => 'UT',
            'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV',
            'wisconsin' => 'WI', 'wyoming' => 'WY',
        ];

        $baseRates = [
            'AL' => 4.0,  'AK' => 0.0,  'AZ' => 5.6,  'AR' => 6.5,  'CA' => 7.25,
            'CO' => 2.9,  'CT' => 6.35, 'DE' => 0.0,  'FL' => 6.0,  'GA' => 4.0,
            'HI' => 4.0,  'ID' => 6.0,  'IL' => 6.25, 'IN' => 7.0,  'IA' => 6.0,
            'KS' => 6.5,  'KY' => 6.0,  'LA' => 4.45, 'ME' => 5.5,  'MD' => 6.0,
            'MA' => 6.25, 'MI' => 6.0,  'MN' => 6.875,'MS' => 7.0,  'MO' => 4.225,
            'MT' => 0.0,  'NE' => 5.5,  'NV' => 6.85, 'NH' => 0.0,  'NJ' => 6.625,
            'NM' => 5.0,  'NY' => 4.0,  'NC' => 4.75, 'ND' => 5.0,  'OH' => 5.75,
            'OK' => 4.5,  'OR' => 0.0,  'PA' => 6.0,  'RI' => 7.0,  'SC' => 6.0,
            'SD' => 4.5,  'TN' => 7.0,  'TX' => 6.25, 'UT' => 4.85, 'VT' => 6.0,
            'VA' => 5.3,  'WA' => 6.5,  'WV' => 6.0,  'WI' => 5.0,  'WY' => 4.0,
        ];

        $key = strtoupper(trim($state));
        if (!isset($baseRates[$key])) {
            $key = $nameToCode[strtolower(trim($state))] ?? null;
        }

        return $key ? ($baseRates[$key] ?? 8.0) : 8.0;
    }
}
