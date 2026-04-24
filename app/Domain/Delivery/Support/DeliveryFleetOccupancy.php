<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Support;

use App\Domain\Delivery\Models\Delivery;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Fleet busy windows aligned with {@see \resources\js\Components\Tenant\DeliveryScheduler.vue} travel + at-location + return travel.
 */
final class DeliveryFleetOccupancy
{
    /**
     * @return list<string>
     */
    public static function statusesExcludedFromFleetConflicts(): array
    {
        return ['cancelled', 'delivered'];
    }

    public static function travelMinutes(?int $seconds): int
    {
        if ($seconds === null || $seconds <= 0) {
            return 0;
        }

        return max(0, (int) round($seconds / 60));
    }

    public static function atLocationMinutes(?int $minutes): int
    {
        if ($minutes === null || $minutes < 1) {
            return 15;
        }

        return min(32767, max(1, $minutes));
    }

    /**
     * Normalize schedule attributes to Carbon (draft models / API payloads may be strings).
     */
    private static function asCarbon(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof CarbonInterface) {
            return $value->copy();
        }
        try {
            return Carbon::parse((string) $value, (string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    public static function deliveriesTableHasFleetColumns(): bool
    {
        $model = new Delivery;

        return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'fleet_truck_id');
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    public static function occupancyWindow(Delivery $d): ?array
    {
        $scheduled = self::asCarbon($d->getAttribute('scheduled_at'));
        if ($scheduled === null) {
            return null;
        }

        $tz = (string) config('app.timezone');
        $scheduled = $scheduled->copy()->timezone($tz);
        $travelMin = self::travelMinutes($d->estimated_travel_duration_seconds !== null ? (int) $d->estimated_travel_duration_seconds : null);
        $atLocMin = self::atLocationMinutes($d->delivery_duration_minutes !== null ? (int) $d->delivery_duration_minutes : null);

        $leaveStart = $scheduled->copy()->subMinutes($travelMin);
        $ttlb = self::asCarbon($d->getAttribute('time_to_leave_by'));
        if ($ttlb !== null) {
            $ttlb = $ttlb->copy()->timezone($tz);
            if ($ttlb->lt($leaveStart)) {
                $leaveStart = $ttlb;
            }
        }

        $end = $scheduled->copy()->addMinutes($atLocMin)->addMinutes($travelMin);

        return [$leaveStart, $end];
    }

    public static function intervalsOverlap(CarbonInterface $a0, CarbonInterface $a1, CarbonInterface $b0, CarbonInterface $b1): bool
    {
        return $a0->lt($b1) && $b0->lt($a1);
    }

    /**
     * @return list<array{id: int, display_name: string, status: string, overlaps_truck: bool, overlaps_trailer: bool}>
     */
    public static function findConflicts(
        ?int $fleetTruckId,
        ?int $fleetTrailerId,
        Delivery $subject,
        ?int $excludeDeliveryId = null
    ): array {
        if ($fleetTruckId === null && $fleetTrailerId === null) {
            return [];
        }

        if (! self::deliveriesTableHasFleetColumns()) {
            return [];
        }

        $subjectWindow = self::occupancyWindow($subject);
        if ($subjectWindow === null) {
            return [];
        }

        /** @var CarbonInterface $s0 */
        /** @var CarbonInterface $s1 */
        [$s0, $s1] = $subjectWindow;

        $q = Delivery::query()
            ->whereNotIn('status', self::statusesExcludedFromFleetConflicts())
            ->whereNotNull('scheduled_at')
            ->where(function ($q2) use ($fleetTruckId, $fleetTrailerId) {
                if ($fleetTruckId !== null && $fleetTrailerId !== null) {
                    $q2->where(function ($q3) use ($fleetTruckId, $fleetTrailerId) {
                        $q3->where('fleet_truck_id', $fleetTruckId)
                            ->orWhere('fleet_trailer_id', $fleetTrailerId);
                    });
                } elseif ($fleetTruckId !== null) {
                    $q2->where('fleet_truck_id', $fleetTruckId);
                } else {
                    $q2->where('fleet_trailer_id', $fleetTrailerId);
                }
            });

        $excludeId = $excludeDeliveryId ?? ($subject->getKey() ?: null);
        if ($excludeId !== null) {
            $q->where('id', '!=', $excludeId);
        }

        $out = [];
        foreach ($q->cursor() as $other) {
            /** @var Delivery $other */
            $w = self::occupancyWindow($other);
            if ($w === null) {
                continue;
            }
            [$o0, $o1] = $w;
            if (! self::intervalsOverlap($s0, $s1, $o0, $o1)) {
                continue;
            }
            $overTruck = $fleetTruckId !== null && (int) $other->fleet_truck_id === (int) $fleetTruckId;
            $overTrail = $fleetTrailerId !== null && (int) $other->fleet_trailer_id === (int) $fleetTrailerId;
            if (! $overTruck && ! $overTrail) {
                continue;
            }
            $out[] = [
                'id' => (int) $other->id,
                'display_name' => $other->display_name,
                'status' => (string) $other->status,
                'overlaps_truck' => $overTruck,
                'overlaps_trailer' => $overTrail,
            ];
        }

        return $out;
    }

    /**
     * Build a temporary Delivery model from validated array for conflict checks before persist.
     *
     * @param  array<string, mixed>  $attrs
     */
    public static function deliveryFromAttributes(array $attrs, ?int $id = null): Delivery
    {
        $d = new Delivery;
        if ($id !== null) {
            $d->id = $id;
        }
        foreach ([
            'scheduled_at', 'time_to_leave_by', 'estimated_travel_duration_seconds',
            'delivery_duration_minutes', 'fleet_truck_id', 'fleet_trailer_id',
        ] as $k) {
            if (! array_key_exists($k, $attrs)) {
                continue;
            }
            $v = $attrs[$k];
            if (($k === 'scheduled_at' || $k === 'time_to_leave_by') && ($v === '' || $v === false)) {
                $v = null;
            }
            $d->setAttribute($k, $v);
        }

        return $d;
    }
}
