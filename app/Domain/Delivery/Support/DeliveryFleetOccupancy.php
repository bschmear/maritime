<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Support;

use App\Domain\Delivery\Models\Delivery;
use App\Models\AccountSettings;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Fleet busy windows aligned with {@see \resources\js\Components\Tenant\DeliveryScheduler.vue} travel + at-location + return travel.
 * Outbound uses {@see Delivery::estimated_travel_duration_seconds}; return uses
 * {@see Delivery::estimated_return_travel_duration_seconds} when set, otherwise matches outbound (legacy).
 */
final class DeliveryFleetOccupancy
{
    /** Max minutes before computed departure that an explicit leave-by time is honored. */
    private const MAX_LEAVE_BY_EARLY_PADDING_MINUTES = 240;

    /**
     * @return list<string>
     */
    public static function statusesExcludedFromFleetConflicts(): array
    {
        return ['cancelled', 'delivered', 'requested'];
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
     * Account timezone for calendar-day boundaries; UTC instants are used for overlap math.
     */
    public static function comparisonTimezone(): string
    {
        try {
            $account = AccountSettings::getCurrent();
            if (is_string($account->timezone ?? null) && $account->timezone !== '') {
                return $account->timezone;
            }
        } catch (\Throwable) {
            // Tenant/account context may be unavailable in tests or CLI.
        }

        return (string) config('app.timezone', 'UTC');
    }

    /**
     * Normalize schedule attributes to a UTC instant (DB values, API ISO strings, Carbon models).
     */
    private static function asUtcInstant(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof CarbonInterface) {
            return $value->copy()->utc();
        }
        try {
            return Carbon::parse((string) $value)->utc();
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
        $scheduled = self::asUtcInstant($d->getAttribute('scheduled_at'));
        if ($scheduled === null) {
            return null;
        }

        $travelOutMin = self::travelMinutes($d->estimated_travel_duration_seconds !== null ? (int) $d->estimated_travel_duration_seconds : null);
        $returnSec = $d->estimated_return_travel_duration_seconds;
        $travelBackMin = self::travelMinutes(
            $returnSec !== null && (int) $returnSec > 0
                ? (int) $returnSec
                : ($d->estimated_travel_duration_seconds !== null ? (int) $d->estimated_travel_duration_seconds : null)
        );
        $atLocMin = self::atLocationMinutes($d->delivery_duration_minutes !== null ? (int) $d->delivery_duration_minutes : null);

        $leaveStart = $scheduled->copy()->subMinutes($travelOutMin);
        $ttlb = self::asUtcInstant($d->getAttribute('time_to_leave_by'));
        if ($ttlb !== null && $ttlb->lt($leaveStart)) {
            $earlyBySeconds = $leaveStart->getTimestamp() - $ttlb->getTimestamp();
            if ($earlyBySeconds <= self::MAX_LEAVE_BY_EARLY_PADDING_MINUTES * 60) {
                $leaveStart = $ttlb->copy();
            }
        }

        $end = $scheduled->copy()->addMinutes($atLocMin)->addMinutes($travelBackMin);

        return [$leaveStart, $end];
    }

    public static function intervalsOverlap(CarbonInterface $a0, CarbonInterface $a1, CarbonInterface $b0, CarbonInterface $b1): bool
    {
        return $a0->getTimestamp() < $b1->getTimestamp()
            && $b0->getTimestamp() < $a1->getTimestamp();
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
            ->where('pending_request', false)
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
     * @return list<array{id: int, display_name: string, status: string, scheduled_at: string|null, window_start: string|null, window_end: string|null}>
     */
    public static function findTechnicianConflicts(
        ?int $technicianId,
        Delivery $subject,
        ?int $excludeDeliveryId = null
    ): array {
        if ($technicianId === null || $technicianId <= 0) {
            return [];
        }

        $subjectWindow = self::occupancyWindow($subject);
        if ($subjectWindow === null) {
            return [];
        }

        /** @var CarbonInterface $s0 */
        /** @var CarbonInterface $s1 */
        [$s0, $s1] = $subjectWindow;

        $out = [];
        foreach (self::technicianDeliveriesQuery($technicianId, $excludeDeliveryId ?? ($subject->getKey() ?: null))->cursor() as $other) {
            /** @var Delivery $other */
            $w = self::occupancyWindow($other);
            if ($w === null) {
                continue;
            }
            [$o0, $o1] = $w;
            if (! self::intervalsOverlap($s0, $s1, $o0, $o1)) {
                continue;
            }
            $out[] = self::technicianDeliveryPayload($other, $o0, $o1, true);
        }

        return $out;
    }

    /**
     * Upcoming deliveries for a driver in a window around the draft schedule.
     *
     * @return list<array{id: int, display_name: string, status: string, scheduled_at: string|null, window_start: string|null, window_end: string|null, conflicts_with_draft: bool}>
     */
    public static function technicianUpcomingSchedule(
        int $technicianId,
        Delivery $subject,
        ?int $excludeDeliveryId = null
    ): array {
        $subjectWindow = self::occupancyWindow($subject);
        if ($subjectWindow === null) {
            return [];
        }

        /** @var CarbonInterface $s0 */
        /** @var CarbonInterface $s1 */
        [$s0, $s1] = $subjectWindow;

        $tz = self::comparisonTimezone();
        $rangeStart = $s0->copy()->timezone($tz)->subDay()->startOfDay()->utc();
        $rangeEnd = $s1->copy()->timezone($tz)->addDays(7)->endOfDay()->utc();

        $conflictIds = array_column(
            self::findTechnicianConflicts($technicianId, $subject, $excludeDeliveryId),
            'id',
        );

        $out = [];
        foreach (self::technicianDeliveriesQuery($technicianId, $excludeDeliveryId ?? ($subject->getKey() ?: null))->cursor() as $delivery) {
            /** @var Delivery $delivery */
            $w = self::occupancyWindow($delivery);
            if ($w === null) {
                continue;
            }
            [$w0, $w1] = $w;
            if ($w1->getTimestamp() < $rangeStart->getTimestamp() || $w0->getTimestamp() > $rangeEnd->getTimestamp()) {
                continue;
            }
            $out[] = self::technicianDeliveryPayload(
                $delivery,
                $w0,
                $w1,
                in_array((int) $delivery->id, $conflictIds, true),
            );
        }

        usort($out, static fn (array $a, array $b) => strcmp((string) ($a['window_start'] ?? ''), (string) ($b['window_start'] ?? '')));

        return $out;
    }

    private static function technicianDeliveriesQuery(int $technicianId, ?int $excludeDeliveryId): \Illuminate\Database\Eloquent\Builder
    {
        $q = Delivery::query()
            ->where('technician_id', $technicianId)
            ->whereNotIn('status', self::statusesExcludedFromFleetConflicts())
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at');

        if ($excludeDeliveryId !== null) {
            $q->where('id', '!=', $excludeDeliveryId);
        }

        return $q;
    }

    /**
     * @return array{id: int, display_name: string, status: string, scheduled_at: string|null, window_start: string|null, window_end: string|null, conflicts_with_draft: bool}
     */
    private static function technicianDeliveryPayload(
        Delivery $delivery,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
        bool $conflictsWithDraft,
    ): array {
        $scheduled = self::asUtcInstant($delivery->getAttribute('scheduled_at'));

        return [
            'id' => (int) $delivery->id,
            'display_name' => $delivery->display_name,
            'status' => (string) $delivery->status,
            'scheduled_at' => $scheduled?->toIso8601String(),
            'window_start' => $windowStart->toIso8601String(),
            'window_end' => $windowEnd->toIso8601String(),
            'conflicts_with_draft' => $conflictsWithDraft,
        ];
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
            'estimated_return_travel_duration_seconds',
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
