<?php

namespace App\Services\Tax;

use App\Models\TaxJurisdictionRate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TaxJurisdictionRateStore
{
    public function __construct(
        protected TaxRateAiService $aiService,
    ) {}

    /**
     * Resolve a stored or AI-fetched tax rate for the address.
     *
     * @param  array{line1?: string, city?: string, state?: string, postal_code?: string, country?: string}  $address
     * @return array{tax_rate: float, tax_rate_decimal: float, jurisdiction_code: string|null, jurisdiction_label: string|null}|null
     */
    public function resolve(array $address): ?array
    {
        $stateCode = $this->normalizeStateCode((string) ($address['state'] ?? ''));
        if ($stateCode === null) {
            return null;
        }

        $key = $this->lookupKey($address);
        $existing = null;

        if ($key !== null && $this->databaseReady()) {
            $existing = TaxJurisdictionRate::query()
                ->where('country_code', $key['country_code'])
                ->where('state_code', $key['state_code'])
                ->where('postal_code', $key['postal_code'])
                ->first();

            if ($existing !== null && ! $existing->isStale()) {
                return $existing->toLookupResult();
            }
        }

        return $this->fetchViaAiAndReturn($address, $key, $existing);
    }

    /**
     * @param  array{line1?: string, city?: string, state?: string, postal_code?: string, country?: string}  $address
     * @return array{country_code: string, state_code: string, postal_code: string}|null
     */
    public function lookupKey(array $address): ?array
    {
        $countryCode = $this->normalizeCountryCode((string) ($address['country'] ?? 'US'));
        $stateCode = $this->normalizeStateCode((string) ($address['state'] ?? ''));
        $postalCode = $this->normalizePostalCode((string) ($address['postal_code'] ?? ''));

        if ($stateCode === null || $postalCode === null) {
            return null;
        }

        return [
            'country_code' => $countryCode,
            'state_code' => $stateCode,
            'postal_code' => $postalCode,
        ];
    }

    /**
     * @param  array{country_code: string, state_code: string, postal_code: string}|null  $key
     * @return array{tax_rate: float, tax_rate_decimal: float, jurisdiction_code: string|null, jurisdiction_label: string|null}|null
     */
    protected function fetchViaAiAndReturn(array $address, ?array $key, ?TaxJurisdictionRate $existing = null): ?array
    {
        try {
            $ai = $this->aiService->fetch($address);
        } catch (\Throwable $e) {
            Log::warning('Tax jurisdiction AI lookup failed', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);

            return $existing?->toLookupResult();
        }

        $storageKey = $key ?? [
            'country_code' => $this->normalizeCountryCode((string) ($address['country'] ?? 'US')),
            'state_code' => $ai['state_code'],
            'postal_code' => $ai['postal_code'],
        ];

        $result = [
            'tax_rate' => round($ai['total_rate_percent'], 4),
            'tax_rate_decimal' => round($ai['total_rate_percent'] / 100, 6),
            'jurisdiction_code' => $ai['jurisdiction_code'],
            'jurisdiction_label' => $ai['jurisdiction_label'],
        ];

        if (! $this->databaseReady() || $storageKey['postal_code'] === '') {
            return $result;
        }

        $record = TaxJurisdictionRate::query()->updateOrCreate(
            [
                'country_code' => $storageKey['country_code'],
                'state_code' => $storageKey['state_code'],
                'postal_code' => $storageKey['postal_code'],
            ],
            [
                'city' => $ai['city'] ?? trim((string) ($address['city'] ?? '')) ?: null,
                'county_name' => $ai['county_name'],
                'jurisdiction_code' => $ai['jurisdiction_code'],
                'jurisdiction_label' => $ai['jurisdiction_label'],
                'state_rate_percent' => $ai['state_rate_percent'],
                'local_rate_percent' => $ai['local_rate_percent'],
                'total_rate_percent' => $ai['total_rate_percent'],
                'source' => 'ai',
                'fetched_at' => now(),
            ],
        );

        return $record->toLookupResult();
    }

    protected function databaseReady(): bool
    {
        try {
            return Schema::connection('pgsql')->hasTable('tax_jurisdiction_rates');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function normalizePostalCode(string $postalCode): ?string
    {
        $digits = preg_replace('/\D/', '', $postalCode) ?? '';

        return strlen($digits) >= 5 ? substr($digits, 0, 5) : null;
    }

    protected function normalizeCountryCode(string $country): string
    {
        $country = trim($country);
        if ($country === '') {
            return 'US';
        }

        if (preg_match('/^[A-Za-z]{2}$/', $country) === 1) {
            return strtoupper($country);
        }

        $key = strtolower(preg_replace('/\s+/', ' ', $country));

        return match ($key) {
            'united states', 'united states of america', 'usa', 'u.s.a.', 'u.s.', 'us' => 'US',
            default => strtoupper($country),
        };
    }

    protected function normalizeStateCode(string $state): ?string
    {
        $state = trim($state);
        if ($state === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]{2}$/', $state) === 1) {
            return strtoupper($state);
        }

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

        return $nameToCode[strtolower($state)] ?? null;
    }
}
