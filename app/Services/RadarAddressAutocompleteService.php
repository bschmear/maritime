<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RadarAddressAutocompleteService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, ?string $countryCode = null, int $limit = 10): array
    {
        $key = config('services.radar.secret') ?: config('services.radar.publishable');
        if (empty($key)) {
            Log::warning('Radar autocomplete requested but no API key is configured');

            return [];
        }

        $params = [
            'query' => $query,
            'layers' => 'address',
            'limit' => min(max($limit, 1), 10),
        ];

        if ($countryCode !== null && $countryCode !== '') {
            $params['countryCode'] = $countryCode;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Authorization' => $key])
                ->get('https://api.radar.io/v1/search/autocomplete', $params);

            if (! $response->successful()) {
                Log::warning('Radar autocomplete HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $addresses = $response->json('addresses') ?? [];

            return array_map(
                fn (array $address): array => $this->normalizeAddress($address),
                is_array($addresses) ? $addresses : [],
            );
        } catch (\Throwable $e) {
            Log::warning('Radar autocomplete request failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function normalizeAddress(array $address): array
    {
        $address['addressLabel'] = $address['placeLabel']
            ?? $address['formattedAddress']
            ?? null;

        return $address;
    }
}
