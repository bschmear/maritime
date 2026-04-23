<?php

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\Delivery;
use App\Domain\Location\Models\Location;
use App\Services\GoogleMapsDirectionsService;
use Illuminate\Support\Facades\Log;

class ComputeDeliveryTravelEstimates
{
    public function __construct(
        private GoogleMapsDirectionsService $directions
    ) {}

    /**
     * Fills {@see Delivery::estimated_travel_duration_seconds} and
     * {@see Delivery::time_to_leave_by} from Google when origin (location) and
     * destination (delivery address) and scheduled time are available.
     *
     * Does not change {@see Delivery::estimated_arrival_at} (set when status → en route).
     */
    public function __invoke(Delivery $delivery): void
    {
        if (in_array($delivery->status, ['en_route', 'delivered', 'cancelled'], true)) {
            return;
        }

        $delivery->loadMissing('location');

        if (! $delivery->location_id || ! $delivery->location || ! $delivery->scheduled_at) {
            $delivery->time_to_leave_by = null;
            $delivery->estimated_travel_duration_seconds = null;

            return;
        }

        if (! $this->destinationIsComplete($delivery)) {
            $delivery->time_to_leave_by = null;
            $delivery->estimated_travel_duration_seconds = null;

            return;
        }

        $origin = $this->formatLocationForDirections($delivery->location);
        $dest = $this->formatDeliveryDestination($delivery);

        if ($origin === null || $dest === null) {
            $delivery->time_to_leave_by = null;
            $delivery->estimated_travel_duration_seconds = null;

            return;
        }

        $seconds = $this->directions->drivingDurationSeconds($origin, $dest);

        if ($seconds === null) {
            Log::info('Delivery travel: no duration from Google', ['delivery_id' => $delivery->id]);
            $delivery->time_to_leave_by = null;
            $delivery->estimated_travel_duration_seconds = null;

            return;
        }

        $delivery->estimated_travel_duration_seconds = $seconds;
        $scheduled = $delivery->scheduled_at;
        if ($scheduled instanceof \DateTimeInterface) {
            $start = $scheduled instanceof \Carbon\Carbon ? $scheduled->copy() : \Carbon\Carbon::parse($scheduled);
            $delivery->time_to_leave_by = $start->subSeconds($seconds);
        } else {
            $delivery->time_to_leave_by = null;
        }
    }

    private function destinationIsComplete(Delivery $delivery): bool
    {
        if ($delivery->latitude != null && $delivery->longitude != null) {
            return true;
        }

        $line1 = trim((string) $delivery->address_line_1);
        $city = trim((string) $delivery->city);
        $state = trim((string) $delivery->state);

        return $line1 !== '' && $city !== '' && $state !== '';
    }

    private function formatLocationForDirections(?Location $location): ?string
    {
        if (! $location) {
            return null;
        }
        if ($location->latitude != null && $location->longitude != null) {
            return ((float) $location->latitude).','.((float) $location->longitude);
        }

        $parts = array_filter([
            $location->address_line_1,
            $location->address_line_2,
            $location->city,
            $location->state,
            $location->postal_code,
            $location->country,
        ], fn ($p) => $p !== null && $p !== '');

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function formatDeliveryDestination(Delivery $delivery): ?string
    {
        if ($delivery->latitude != null && $delivery->longitude != null) {
            return ((float) $delivery->latitude).','.((float) $delivery->longitude);
        }

        $parts = array_filter([
            $delivery->address_line_1,
            $delivery->address_line_2,
            $delivery->city,
            $delivery->state,
            $delivery->postal_code,
            $delivery->country,
        ], fn ($p) => $p !== null && $p !== '');

        if (count($parts) < 2) {
            return null;
        }

        return implode(', ', $parts);
    }

    /**
     * Live preview for the delivery form (unsaved or partial state).
     *
     * @return array{duration_seconds: int, time_to_leave_by: string|null}|null
     */
    public function previewFromInputs(Location $location, array $dest, string $scheduledAtIso): ?array
    {
        $origin = $this->formatLocationForDirections($location);
        if ($origin === null) {
            return null;
        }

        $tmp = new Delivery;
        $tmp->address_line_1 = $dest['address_line_1'] ?? null;
        $tmp->address_line_2 = $dest['address_line_2'] ?? null;
        $tmp->city = $dest['city'] ?? null;
        $tmp->state = $dest['state'] ?? null;
        $tmp->postal_code = $dest['postal_code'] ?? null;
        $tmp->country = $dest['country'] ?? null;
        $tmp->latitude = $dest['latitude'] ?? null;
        $tmp->longitude = $dest['longitude'] ?? null;
        $tmp->scheduled_at = $scheduledAtIso;

        if (! $this->destinationIsComplete($tmp)) {
            return null;
        }

        $destination = $this->formatDeliveryDestination($tmp);
        if ($destination === null) {
            return null;
        }

        $seconds = $this->directions->drivingDurationSeconds($origin, $destination);
        if ($seconds === null) {
            return null;
        }

        $start = \Carbon\Carbon::parse($scheduledAtIso);
        $leaveBy = $start->copy()->subSeconds($seconds);

        return [
            'duration_seconds' => $seconds,
            'time_to_leave_by' => $leaveBy->toIso8601String(),
        ];
    }
}
