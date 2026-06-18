<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Delivery\Models\Delivery;
use App\Domain\Delivery\Support\DeliveryFleetOccupancy;
use Carbon\Carbon;
use Tests\TestCase;

class DeliveryFleetOccupancyTest extends TestCase
{
    public function test_occupancy_window_ignores_stale_time_to_leave_by(): void
    {
        $delivery = new Delivery;
        $delivery->scheduled_at = Carbon::parse('2026-06-18 14:00:00', 'UTC');
        $delivery->estimated_travel_duration_seconds = 3600;
        $delivery->estimated_return_travel_duration_seconds = 3600;
        $delivery->delivery_duration_minutes = 15;
        $delivery->time_to_leave_by = Carbon::parse('2026-06-18 07:00:00', 'UTC');

        [$start, $end] = DeliveryFleetOccupancy::occupancyWindow($delivery);

        $this->assertSame('13:00', $start->copy()->utc()->format('H:i'));
        $this->assertSame('15:15', $end->copy()->utc()->format('H:i'));
    }

    public function test_occupancy_window_honors_reasonable_early_leave_by(): void
    {
        $delivery = new Delivery;
        $delivery->scheduled_at = Carbon::parse('2026-06-18 14:00:00', 'UTC');
        $delivery->estimated_travel_duration_seconds = 3600;
        $delivery->estimated_return_travel_duration_seconds = 3600;
        $delivery->delivery_duration_minutes = 15;
        $delivery->time_to_leave_by = Carbon::parse('2026-06-18 11:00:00', 'UTC');

        [$start] = DeliveryFleetOccupancy::occupancyWindow($delivery);

        $this->assertSame('11:00', $start->copy()->utc()->format('H:i'));
    }

    public function test_non_overlapping_same_day_windows_do_not_conflict(): void
    {
        $morning = new Delivery;
        $morning->scheduled_at = Carbon::parse('2026-06-18 09:00:00', 'UTC');
        $morning->estimated_travel_duration_seconds = 3600;
        $morning->estimated_return_travel_duration_seconds = 3600;
        $morning->delivery_duration_minutes = 60;

        $afternoon = new Delivery;
        $afternoon->scheduled_at = Carbon::parse('2026-06-18 15:00:00', 'UTC');
        $afternoon->estimated_travel_duration_seconds = 3600;
        $afternoon->estimated_return_travel_duration_seconds = 3600;
        $afternoon->delivery_duration_minutes = 60;

        [$m0, $m1] = DeliveryFleetOccupancy::occupancyWindow($morning);
        [$a0, $a1] = DeliveryFleetOccupancy::occupancyWindow($afternoon);

        $this->assertFalse(DeliveryFleetOccupancy::intervalsOverlap($m0, $m1, $a0, $a1));
    }

    public function test_scheduled_deliveries_three_hours_apart_with_local_travel_do_not_overlap(): void
    {
        $earlier = new Delivery;
        $earlier->id = 2;
        $earlier->scheduled_at = Carbon::parse('2026-06-18 17:00:00', 'UTC');
        $earlier->time_to_leave_by = Carbon::parse('2026-06-18 16:49:47', 'UTC');
        $earlier->estimated_travel_duration_seconds = 613;
        $earlier->estimated_return_travel_duration_seconds = 684;

        $laterRequest = new Delivery;
        $laterRequest->id = 3;
        $laterRequest->scheduled_at = Carbon::parse('2026-06-18 20:15:00', 'UTC');
        $laterRequest->fleet_truck_id = 1;

        [$e0, $e1] = DeliveryFleetOccupancy::occupancyWindow($earlier);
        [$l0, $l1] = DeliveryFleetOccupancy::occupancyWindow($laterRequest);

        $this->assertFalse(DeliveryFleetOccupancy::intervalsOverlap($l0, $l1, $e0, $e1));
    }

    public function test_api_utc_iso_payload_matches_stored_delivery_window(): void
    {
        $iso = '2026-06-18T18:00:00.000000Z';

        $fromApi = DeliveryFleetOccupancy::deliveryFromAttributes([
            'scheduled_at' => $iso,
            'estimated_travel_duration_seconds' => 3600,
            'estimated_return_travel_duration_seconds' => 3600,
            'delivery_duration_minutes' => 60,
        ], 10);

        $stored = new Delivery;
        $stored->id = 20;
        $stored->scheduled_at = Carbon::parse($iso);
        $stored->estimated_travel_duration_seconds = 3600;
        $stored->estimated_return_travel_duration_seconds = 3600;
        $stored->delivery_duration_minutes = 60;

        [$apiStart, $apiEnd] = DeliveryFleetOccupancy::occupancyWindow($fromApi);
        [$dbStart, $dbEnd] = DeliveryFleetOccupancy::occupancyWindow($stored);

        $this->assertSame($dbStart->getTimestamp(), $apiStart->getTimestamp());
        $this->assertSame($dbEnd->getTimestamp(), $apiEnd->getTimestamp());
    }

    public function test_same_account_day_non_overlapping_eastern_times_do_not_conflict(): void
    {
        $morning = DeliveryFleetOccupancy::deliveryFromAttributes([
            'scheduled_at' => '2026-06-18T13:00:00.000000Z', // 9:00 AM EDT
            'estimated_travel_duration_seconds' => 3600,
            'estimated_return_travel_duration_seconds' => 3600,
            'delivery_duration_minutes' => 60,
        ]);

        $afternoon = new Delivery;
        $afternoon->scheduled_at = Carbon::parse('2026-06-18T19:00:00.000000Z'); // 3:00 PM EDT
        $afternoon->estimated_travel_duration_seconds = 3600;
        $afternoon->estimated_return_travel_duration_seconds = 3600;
        $afternoon->delivery_duration_minutes = 60;

        [$m0, $m1] = DeliveryFleetOccupancy::occupancyWindow($morning);
        [$a0, $a1] = DeliveryFleetOccupancy::occupancyWindow($afternoon);

        $this->assertFalse(DeliveryFleetOccupancy::intervalsOverlap($m0, $m1, $a0, $a1));
    }
}
