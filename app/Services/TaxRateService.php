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
        return $this->lookupByLocation($location)['tax_rate_decimal'] ?? null;
    }

    /**
     * Tax lookup for a saved location (rate + compact jurisdiction code for QBO / reporting).
     *
     * @return array{tax_rate: float|null, tax_rate_decimal: float|null, jurisdiction_code: string|null, jurisdiction_label: string|null}
     */
    public function lookupByLocation(Location $location): array
    {
        if ($location->tax_rate && $location->tax_last_fetched_at) {
            if (Carbon::parse($location->tax_last_fetched_at)->gt(now()->subDay())) {
                $code = $this->normalizeStateCode((string) ($location->state ?? ''));

                return $this->formatLookupResult(
                    $location->tax_rate,
                    $code,
                    $this->buildJurisdictionLabel([
                        'city' => $location->city ?? '',
                        'state' => $location->state ?? '',
                        'postal_code' => $location->postal_code ?? '',
                        'country' => $location->country ?? 'US',
                    ]) ?: ($code !== null ? $this->stateLabel($code) : null),
                );
            }
        }

        $address = [
            'line1'       => $location->address_line1 ?? $location->address ?? '',
            'city'        => $location->city ?? '',
            'state'       => $location->state ?? '',
            'postal_code' => $location->postal_code ?? '',
            'country'     => $location->country ?? 'US',
        ];

        $lookup = $this->lookupByAddress($address);

        if ($lookup['tax_rate_decimal'] !== null) {
            $location->tax_rate = $lookup['tax_rate_decimal'];
            $location->tax_last_fetched_at = now()->startOfDay()->addDay();
            $location->save();
        }

        return $lookup;
    }

    /**
     * Get tax rate from raw address fields (no Location model required).
     * Used by estimates and any other record with a billing address.
     *
     * Returns the rate as a percentage (e.g. 7.0 for 7%).
     */
    public function getTaxRateByAddress(
        string $state,
        ?string $city = null,
        ?string $postalCode = null,
        ?string $country = 'US',
        ?string $line1 = ''
    ): float {
        return $this->lookupByAddress([
            'line1' => $line1 ?? '',
            'city' => $city ?? '',
            'state' => $state,
            'postal_code' => $postalCode ?? '',
            'country' => $country ?? 'US',
        ])['tax_rate'] ?? $this->fallbackStateRate($state);
    }

    /**
     * @param  array{line1?: string, city?: string, state?: string, postal_code?: string, country?: string}  $address
     * @return array{tax_rate: float, tax_rate_decimal: float|null, jurisdiction_code: string|null, jurisdiction_label: string|null}
     */
    public function lookupByAddress(array $address): array
    {
        $stateInput = (string) ($address['state'] ?? '');
        $jurisdictionCode = $this->normalizeStateCode($stateInput);

        $stripe = $this->fetchFromStripe($address);

        $label = $this->buildJurisdictionLabel($address);

        if ($stripe['rate_decimal'] !== null) {
            $code = $stripe['jurisdiction_code'] ?? $jurisdictionCode;

            return $this->formatLookupResult(
                $stripe['rate_decimal'],
                $code,
                $label !== '' ? $label : ($code !== null ? $this->stateLabel($code) : null),
            );
        }

        $fallbackPercent = $this->fallbackStateRate($stateInput);
        $code = $jurisdictionCode ?? $this->normalizeStateCode($this->stateCodeFromName($stateInput));

        return [
            'tax_rate' => $fallbackPercent,
            'tax_rate_decimal' => round($fallbackPercent / 100, 6),
            'jurisdiction_code' => $code,
            'jurisdiction_label' => $label !== '' ? $label : ($code !== null ? $this->stateLabel($code) : null),
        ];
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
     * effective tax rate and jurisdiction for the given address.
     *
     * @return array{rate_decimal: float|null, jurisdiction_code: string|null, jurisdiction_label: string|null}
     */
    protected function fetchFromStripe(array $address): array
    {
        $address = array_filter($address, fn ($v) => $v !== null && $v !== '');

        if (empty($address['state']) && empty($address['postal_code'])) {
            return ['rate_decimal' => null, 'jurisdiction_code' => null, 'jurisdiction_label' => null];
        }

        try {
            $calculation = $this->stripe->tax->calculations->create([
                'currency' => 'usd',
                'customer_details' => [
                    'address' => array_merge(['country' => 'US'], $address),
                    'address_source' => 'billing',
                ],
                'line_items' => [
                    [
                        'amount' => 10000,
                        'reference' => 'tax_rate_lookup',
                        'tax_behavior' => 'exclusive',
                        'tax_code' => 'txcd_99999999',
                    ],
                ],
            ]);

            $taxCents = $calculation->tax_amount_exclusive ?? 0;
            $baseCents = 10000;
            $rateDecimal = $baseCents > 0 ? round($taxCents / $baseCents, 6) : null;
            $jurisdiction = $this->jurisdictionFromStripeCalculation($calculation, (string) ($address['state'] ?? ''));

            return [
                'rate_decimal' => $rateDecimal,
                'jurisdiction_code' => $jurisdiction['code'],
                'jurisdiction_label' => $jurisdiction['label'],
            ];
        } catch (\Exception $e) {
            logger()->error('Stripe Tax API error', [
                'message' => $e->getMessage(),
                'address' => $address,
            ]);

            return ['rate_decimal' => null, 'jurisdiction_code' => null, 'jurisdiction_label' => null];
        }
    }

    /**
     * @return array{code: string|null, label: string|null}
     */
    protected function jurisdictionFromStripeCalculation(object $calculation, string $fallbackState): array
    {
        $code = $this->normalizeStateCode($fallbackState);
        $label = null;

        foreach ($calculation->tax_breakdown ?? [] as $row) {
            $details = $row->tax_rate_details ?? null;
            if ($details === null) {
                continue;
            }

            $state = $this->normalizeStateCode((string) ($details->state ?? ''));
            if ($state !== null) {
                $code = $state;
                $label = $this->stateLabel($state);
                break;
            }
        }

        if ($code === null) {
            $addrState = $calculation->customer_details->address->state ?? null;
            $code = $this->normalizeStateCode((string) ($addrState ?? ''));
            $label = $code !== null ? $this->stateLabel($code) : null;
        }

        return ['code' => $code, 'label' => $label];
    }

    /**
     * @return array{tax_rate: float, tax_rate_decimal: float|null, jurisdiction_code: string|null, jurisdiction_label: string|null}
     */
    protected function formatLookupResult(?float $rateDecimal, ?string $code, ?string $label): array
    {
        return [
            'tax_rate' => $rateDecimal !== null ? round($rateDecimal * 100, 4) : null,
            'tax_rate_decimal' => $rateDecimal,
            'jurisdiction_code' => $code,
            'jurisdiction_label' => $label,
        ];
    }

    /**
     * Human-readable jurisdiction for invoices, deals, and estimates (may include city + ZIP).
     *
     * @param  array{line1?: string, city?: string, state?: string, postal_code?: string, country?: string}  $address
     */
    public function buildJurisdictionLabel(array $address): string
    {
        $city = trim((string) ($address['city'] ?? ''));
        $state = trim((string) ($address['state'] ?? ''));
        $postal = trim((string) ($address['postal_code'] ?? ''));
        $country = trim((string) ($address['country'] ?? 'US'));

        if ($city !== '' && $state !== '') {
            $base = implode(', ', array_filter([$city, $state, $postal]));

            return ($country !== '' && strtoupper($country) !== 'US')
                ? "{$base} ({$country})"
                : $base;
        }

        if ($state !== '' && $postal !== '') {
            return "{$state} {$postal}";
        }

        if ($state !== '') {
            return ($country !== '' && strtoupper($country) !== 'US')
                ? "{$state} ({$country})"
                : $state;
        }

        return '';
    }

    public function normalizeStateCode(string $state): ?string
    {
        $state = trim($state);
        if ($state === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]{2}$/', $state) === 1) {
            return strtoupper($state);
        }

        return $this->stateCodeFromName($state);
    }

    public function stateLabel(string $code): string
    {
        $labels = [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
            'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
            'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
            'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
            'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
            'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
            'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
            'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
            'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
            'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
            'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
            'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
            'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
        ];

        $upper = strtoupper(trim($code));

        return $labels[$upper] ?? $upper;
    }

    protected function stateCodeFromName(string $state): ?string
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

        $key = strtolower(trim($state));

        return $nameToCode[$key] ?? null;
    }

    /**
     * Last-resort fallback used only when Stripe is unavailable.
     * Returns a percentage (e.g. 7.0).
     */
    protected function fallbackStateRate(string $state): float
    {
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

        $key = $this->normalizeStateCode($state);

        return $key ? ($baseRates[$key] ?? 8.0) : 8.0;
    }
}
