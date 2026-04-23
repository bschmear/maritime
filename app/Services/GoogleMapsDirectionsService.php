<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsDirectionsService
{
    /**
     * Driving duration in seconds between two address strings, or null on failure.
     * Uses the Directions API (static duration; enable Distance Matrix + traffic in Google Cloud if needed).
     */
    public function drivingDurationSeconds(string $origin, string $destination): ?int
    {
        $key = config('services.google.maps_api_key');
        if (empty($key) || $origin === '' || $destination === '') {
            return null;
        }

        $params = [
            'origin' => $origin,
            'destination' => $destination,
            'key' => $key,
        ];

        try {
            $response = Http::timeout(12)->get('https://maps.googleapis.com/maps/api/directions/json', $params);

            if (! $response->successful()) {
                Log::warning('Google Directions HTTP error', ['status' => $response->status()]);

                return null;
            }

            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK' || empty($data['routes'][0]['legs'][0])) {
                Log::info('Google Directions not OK', ['status' => $data['status'] ?? 'unknown']);

                return null;
            }

            $leg = $data['routes'][0]['legs'][0];

            if (isset($leg['duration']['value'])) {
                return (int) $leg['duration']['value'];
            }
        } catch (\Throwable $e) {
            Log::warning('Google Directions request failed', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
