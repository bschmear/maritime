<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Delivery\Models\Delivery;
use App\Domain\User\Models\User;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use App\Models\AccountSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SchedulingController extends Controller
{
    private const RANGE_WEEKS_PAST = 6;

    private const RANGE_WEEKS_FUTURE = 14;

    public function index()
    {
        $technicians = User::query()
            ->where('is_technician', true)
            ->with(['locations' => fn ($q) => $q->select('locations.id', 'locations.display_name')])
            ->orderBy('display_name')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->display_name ?: trim($u->first_name.' '.$u->last_name) ?: $u->email,
                'location' => $u->locations->pluck('display_name')->filter()->unique()->implode(', ') ?: '—',
            ])
            ->values();

        $techIds = $technicians->pluck('id')->all();

        $rangeStart = now()->startOfWeek()->subWeeks(self::RANGE_WEEKS_PAST)->startOfDay();
        $rangeEnd = now()->startOfWeek()->addWeeks(self::RANGE_WEEKS_FUTURE)->endOfDay();

        $workOrderRows = collect();
        $deliveryRows = collect();

        if ($techIds !== []) {
            $workOrderRows = WorkOrder::query()
                ->whereIn('assigned_user_id', $techIds)
                ->where(function ($q) {
                    $q->whereNotNull('scheduled_start_at')
                        ->orWhereNotNull('due_at');
                })
                ->where(function ($q) use ($rangeStart, $rangeEnd) {
                    $q->whereBetween('scheduled_start_at', [$rangeStart, $rangeEnd])
                        ->orWhereBetween('due_at', [$rangeStart, $rangeEnd]);
                })
                ->get()
                ->map(fn (WorkOrder $wo) => $this->mapWorkOrderRow($wo))
                ->filter()
                ->values();

            $deliveryRows = Delivery::query()
                ->whereIn('technician_id', $techIds)
                ->whereNotNull('scheduled_at')
                ->whereBetween('scheduled_at', [$rangeStart, $rangeEnd])
                ->with('customer')
                ->get()
                ->map(fn (Delivery $d) => $this->mapDeliveryRow($d))
                ->filter()
                ->values();
        }

        $workOrders = $workOrderRows->concat($deliveryRows)->values()->all();

        $locationNames = $technicians
            ->pluck('location')
            ->filter(fn ($l) => $l && $l !== '—')
            ->flatMap(fn ($l) => array_map('trim', explode(',', $l)))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $locations = array_values(array_unique(array_merge(['All Locations'], $locationNames)));

        $account = AccountSettings::getCurrent();

        return inertia('Tenant/ServiceYard/Scheduling', [
            'technicians' => $technicians->all(),
            'workOrders' => $workOrders,
            'locations' => $locations,
            'scheduleDefaults' => [
                'workday_hours' => (int) ($account->workday_hours ?? 6),
                'workday_start_hour' => $this->hourFromStartTime($account->start_time ?? '08:00:00'),
                'allow_overlap' => (bool) ($account->allow_overlap ?? false),
            ],
        ]);
    }

    /**
     * Map account start_time to the scheduler toolbar hour (6–9) used by SchedulerGrid.
     */
    private function hourFromStartTime(mixed $value): int
    {
        if ($value instanceof Carbon) {
            $h = (int) $value->format('G');
        } else {
            $s = (string) $value;
            $h = (int) explode(':', $s)[0];
        }

        return min(9, max(6, $h));
    }

    /**
     * Persist technician assignment and scheduled start from the scheduling board (JSON).
     */
    public function updateItem(Request $request)
    {
        $data = $request->validate([
            'record_type' => 'required|in:work_order,delivery',
            'record_id' => 'required|integer|min:1',
            'technician_id' => 'required|integer',
            'scheduled_at' => 'required|date',
        ]);

        if (! User::query()->whereKey($data['technician_id'])->where('is_technician', true)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Selected user is not a technician.',
            ], 422);
        }

        $tz = config('app.timezone');
        $scheduledAt = Carbon::parse($data['scheduled_at'], $tz)->timezone($tz);

        if ($data['record_type'] === 'work_order') {
            $wo = WorkOrder::query()->findOrFail($data['record_id']);

            if ($wo->scheduled_start_at && $wo->scheduled_end_at) {
                $delta = $scheduledAt->getTimestamp() - $wo->scheduled_start_at->getTimestamp();
                $wo->scheduled_end_at = $wo->scheduled_end_at->copy()->addSeconds($delta);
            }

            $wo->scheduled_start_at = $scheduledAt;
            $wo->assigned_user_id = $data['technician_id'];
            $wo->save();

            $mapped = $this->mapWorkOrderRow($wo->fresh());
        } else {
            $delivery = Delivery::query()->findOrFail($data['record_id']);
            $oldScheduled = $delivery->scheduled_at?->copy();

            $delivery->scheduled_at = $scheduledAt;
            $delivery->technician_id = $data['technician_id'];

            if ($oldScheduled && $delivery->estimated_arrival_at) {
                $delta = $scheduledAt->getTimestamp() - $oldScheduled->getTimestamp();
                $delivery->estimated_arrival_at = $delivery->estimated_arrival_at->copy()->addSeconds($delta);
            }

            $delivery->save();

            $mapped = $this->mapDeliveryRow($delivery->fresh(['customer']));
        }

        if ($mapped === null) {
            return response()->json([
                'success' => false,
                'message' => 'Record no longer appears on the schedule (check assignment and dates).',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'item' => $mapped,
        ]);
    }

    /**
     * Persist workday length, start hour, and overlap from the scheduling toolbar.
     */
    public function updateDefaults(Request $request)
    {
        $data = $request->validate([
            'workday_hours' => 'required|integer|min:4|max:10',
            'workday_start_hour' => 'required|integer|min:6|max:9',
            'allow_overlap' => 'required|boolean',
        ]);

        $account = AccountSettings::getCurrent();
        $account->workday_hours = $data['workday_hours'];
        $account->start_time = sprintf('%02d:00:00', $data['workday_start_hour']);
        $account->allow_overlap = (bool) $data['allow_overlap'];
        $account->save();

        return response()->json([
            'success' => true,
            'schedule_defaults' => [
                'workday_hours' => (int) $account->workday_hours,
                'workday_start_hour' => $this->hourFromStartTime($account->start_time),
                'allow_overlap' => (bool) $account->allow_overlap,
            ],
        ]);
    }

    private function formatScheduleLocal(?Carbon $dt): string
    {
        if (! $dt) {
            return '';
        }

        return $dt->copy()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapWorkOrderRow(WorkOrder $wo): ?array
    {
        if (! $wo->assigned_user_id) {
            return null;
        }

        $start = $wo->scheduled_start_at ?? $wo->due_at;
        if (! $start) {
            return null;
        }

        $start = $start instanceof Carbon ? $start->copy() : Carbon::parse($start);

        $plannedHours = (float) ($wo->estimated_hours ?? 0);
        if ($plannedHours < 0.25) {
            $plannedHours = 1.0;
        }

        $status = $this->workOrderStatusValue($wo->status);

        $num = $wo->work_order_number;
        $title = $num
            ? 'WO-'.$num.' '.($wo->display_name ?? '')
            : ($wo->display_name ?? 'Work Order #'.$wo->id);

        return [
            'id' => 'wo-'.$wo->id,
            'record_id' => $wo->id,
            'record_type' => 'work_order',
            'title' => trim($title) ?: 'Work Order #'.$wo->id,
            'type' => 'work_order',
            'technician_id' => (int) $wo->assigned_user_id,
            'start_date' => $start->format('Y-m-d'),
            'scheduled_at_local' => $this->formatScheduleLocal($start),
            'status' => $status,
            'planned_hours' => $plannedHours,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapDeliveryRow(Delivery $d): ?array
    {
        if (! $d->technician_id || ! $d->scheduled_at) {
            return null;
        }

        $plannedHours = 2.0;
        if ($d->scheduled_at && $d->estimated_arrival_at) {
            $mins = $d->scheduled_at->diffInMinutes($d->estimated_arrival_at);
            if ($mins > 0) {
                $plannedHours = max(0.25, round($mins / 60, 2));
            }
        }

        $status = is_string($d->status) ? $d->status : (string) $d->status;
        $customer = $d->customer?->display_name;
        $title = trim($d->display_name.($customer ? ' · '.$customer : ''));

        return [
            'id' => 'dlv-'.$d->id,
            'record_id' => $d->id,
            'record_type' => 'delivery',
            'title' => $title ?: $d->display_name,
            'type' => 'delivery',
            'technician_id' => (int) $d->technician_id,
            'start_date' => $d->scheduled_at->format('Y-m-d'),
            'scheduled_at_local' => $this->formatScheduleLocal($d->scheduled_at),
            'status' => $status,
            'planned_hours' => $plannedHours,
        ];
    }

    private function workOrderStatusValue(mixed $status): string
    {
        $id = (int) $status;
        foreach (WorkOrderStatus::cases() as $case) {
            if ($case->id() === $id) {
                return $case->value;
            }
        }

        return 'open';
    }
}
