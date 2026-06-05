<?php

namespace App\Services\ServiceYard;

use App\Domain\Customer\Models\Customer;
use App\Domain\Location\Models\Location;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\User\Models\User;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\ServiceTicket\Status as ServiceTicketStatus;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ServiceYardOverviewDataService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $openTicketStatusIds = $this->openTicketStatusIds();
        $openWorkOrderStatusIds = $this->openWorkOrderStatusIds();
        $locationId = $this->resolveLocationId($request);
        $technicianId = $this->resolveTechnicianId($request);

        $ticketStatusOptions = ServiceTicketStatus::options();
        $workOrderStatusOptions = WorkOrderStatus::options();

        $ticketBaseQuery = $this->scopeServiceTicket(
            ServiceTicket::query()->whereIn('status', $openTicketStatusIds),
            $locationId,
            $technicianId,
            $openWorkOrderStatusIds,
        );

        $workOrderBaseQuery = $this->scopeWorkOrder(
            WorkOrder::query()->whereIn('status', $openWorkOrderStatusIds),
            $locationId,
            $technicianId,
        );

        $overEstimateCount = $this->countWorkOrdersByHoursBucket($workOrderBaseQuery, 'over');
        $underEstimateCount = $this->countWorkOrdersByHoursBucket($workOrderBaseQuery, 'under');

        return [
            'filters' => [
                'location_id' => $locationId,
                'technician_id' => $technicianId,
            ],
            'locations' => $this->locationOptions(),
            'technicians' => $this->technicianOptions($openWorkOrderStatusIds),
            'summary' => [
                [
                    'key' => 'open_tickets',
                    'label' => 'Open tickets',
                    'value' => (clone $ticketBaseQuery)->count(),
                    'hint' => 'Open and in progress',
                    'icon' => 'confirmation_number',
                    'color' => 'blue',
                    'href' => $this->safeRoute('servicetickets.index'),
                ],
                [
                    'key' => 'open_work_orders',
                    'label' => 'Open work orders',
                    'value' => (clone $workOrderBaseQuery)->count(),
                    'hint' => 'Active yard work',
                    'icon' => 'build',
                    'color' => 'indigo',
                    'href' => $this->safeRoute('workorders.index'),
                ],
                [
                    'key' => 'over_estimate',
                    'label' => 'Over estimate',
                    'value' => $overEstimateCount,
                    'hint' => 'Actual hours above estimate',
                    'icon' => 'schedule',
                    'color' => 'red',
                ],
                [
                    'key' => 'under_estimate',
                    'label' => 'Under estimate',
                    'value' => $underEstimateCount,
                    'hint' => 'Actual hours below estimate',
                    'icon' => 'timelapse',
                    'color' => 'green',
                ],
            ],
            'locationSections' => $this->locationSections(
                $openTicketStatusIds,
                $openWorkOrderStatusIds,
                $locationId,
                $technicianId,
            ),
            'enumOptions' => [
                'App\\Enums\\ServiceTicket\\Status' => $ticketStatusOptions,
                'App\\Enums\\WorkOrder\\Status' => $workOrderStatusOptions,
            ],
            'chartData' => [
                'service_tickets_by_status' => $this->statusChartPayload(
                    clone $ticketBaseQuery,
                    'status',
                    $ticketStatusOptions,
                    $openTicketStatusIds,
                ),
                'work_orders_by_status' => $this->statusChartPayload(
                    clone $workOrderBaseQuery,
                    'status',
                    $workOrderStatusOptions,
                    $openWorkOrderStatusIds,
                ),
                'work_orders_hours_variance' => $this->workOrdersHoursVarianceChart(clone $workOrderBaseQuery),
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function openTicketStatusIds(): array
    {
        return [
            ServiceTicketStatus::Open->id(),
            ServiceTicketStatus::InProgress->id(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function openWorkOrderStatusIds(): array
    {
        return [
            WorkOrderStatus::Open->id(),
            WorkOrderStatus::Scheduled->id(),
            WorkOrderStatus::InProgress->id(),
            WorkOrderStatus::Waiting->id(),
            WorkOrderStatus::Blocked->id(),
        ];
    }

    private function resolveLocationId(Request $request): ?int
    {
        $id = $request->query('location_id');
        if ($id === null || $id === '' || $id === 'all') {
            return null;
        }

        $id = (int) $id;

        return $id > 0 ? $id : null;
    }

    private function resolveTechnicianId(Request $request): ?int
    {
        $id = $request->query('technician_id');
        if ($id === null || $id === '' || $id === 'all') {
            return null;
        }

        $id = (int) $id;

        return $id > 0 ? $id : null;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function locationOptions(): array
    {
        return Location::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name'])
            ->map(fn (Location $location) => [
                'id' => (int) $location->id,
                'label' => trim((string) ($location->display_name ?: 'Location #'.$location->id)),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $openWorkOrderStatusIds
     * @return list<array{id: int, label: string}>
     */
    private function technicianOptions(array $openWorkOrderStatusIds): array
    {
        $ids = WorkOrder::query()
            ->whereIn('status', $openWorkOrderStatusIds)
            ->whereNotNull('assigned_user_id')
            ->distinct()
            ->pluck('assigned_user_id');

        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'first_name', 'last_name'])
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'label' => $this->userDisplayName($user),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<ServiceTicket>  $query
     * @param  array<int, int>  $openWorkOrderStatusIds
     * @return Builder<ServiceTicket>
     */
    private function scopeServiceTicket(
        Builder $query,
        ?int $locationId,
        ?int $technicianId,
        array $openWorkOrderStatusIds,
    ): Builder {
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        if ($technicianId) {
            $query->whereHas('workOrders', function (Builder $wo) use ($technicianId, $openWorkOrderStatusIds, $locationId) {
                $wo->whereIn('status', $openWorkOrderStatusIds)
                    ->where('assigned_user_id', $technicianId);
                if ($locationId) {
                    $wo->where('location_id', $locationId);
                }
            });
        }

        return $query;
    }

    /**
     * @param  Builder<WorkOrder>  $query
     * @return Builder<WorkOrder>
     */
    private function scopeWorkOrder(Builder $query, ?int $locationId, ?int $technicianId): Builder
    {
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        if ($technicianId) {
            $query->where('assigned_user_id', $technicianId);
        }

        return $query;
    }

    /**
     * @param  array<int, int>  $openTicketStatusIds
     * @param  array<int, int>  $openWorkOrderStatusIds
     * @return list<array<string, mixed>>
     */
    private function locationSections(
        array $openTicketStatusIds,
        array $openWorkOrderStatusIds,
        ?int $locationId,
        ?int $technicianId,
    ): array {
        $sections = [];
        $locations = Location::query()->orderBy('display_name')->get(['id', 'display_name']);

        if ($locationId) {
            $locations = $locations->where('id', $locationId)->values();
        }

        foreach ($locations as $location) {
            $serviceTickets = $this->loadTicketsForLocation(
                (int) $location->id,
                $openTicketStatusIds,
                $openWorkOrderStatusIds,
                $technicianId,
            );
            $standaloneWorkOrders = $this->loadStandaloneWorkOrdersForLocation(
                (int) $location->id,
                $openWorkOrderStatusIds,
                $openTicketStatusIds,
                $technicianId,
            );

            if ($serviceTickets->isEmpty() && $standaloneWorkOrders->isEmpty()) {
                continue;
            }

            $sections[] = [
                'location' => [
                    'id' => $location->id,
                    'display_name' => $location->display_name,
                ],
                'service_tickets' => $serviceTickets->values()->all(),
                'standalone_work_orders' => $standaloneWorkOrders->values()->all(),
            ];
        }

        if ($locationId === null) {
            $unassignedTickets = $this->loadTicketsForLocation(
                null,
                $openTicketStatusIds,
                $openWorkOrderStatusIds,
                $technicianId,
            );
            $unassignedStandalone = $this->loadStandaloneWorkOrdersForLocation(
                null,
                $openWorkOrderStatusIds,
                $openTicketStatusIds,
                $technicianId,
            );

            if ($unassignedTickets->isNotEmpty() || $unassignedStandalone->isNotEmpty()) {
                $sections[] = [
                    'location' => [
                        'id' => null,
                        'display_name' => 'No location assigned',
                    ],
                    'service_tickets' => $unassignedTickets->values()->all(),
                    'standalone_work_orders' => $unassignedStandalone->values()->all(),
                ];
            }
        }

        return $sections;
    }

    /**
     * @param  array<int, int>  $openTicketStatusIds
     * @param  array<int, int>  $openWorkOrderStatusIds
     * @return Collection<int, array<string, mixed>>
     */
    private function loadTicketsForLocation(
        ?int $locationId,
        array $openTicketStatusIds,
        array $openWorkOrderStatusIds,
        ?int $technicianId,
    ): Collection {
        $query = ServiceTicket::query()
            ->with(array_merge($this->serviceTicketRelations(), [
                'workOrders' => function ($q) use ($openWorkOrderStatusIds, $technicianId) {
                    $q->with($this->workOrderRelations())
                        ->whereIn('status', $openWorkOrderStatusIds)
                        ->when($technicianId, fn (Builder $wo) => $wo->where('assigned_user_id', $technicianId))
                        ->orderByRaw(
                            'CASE WHEN status = ? THEN 0 ELSE 1 END',
                            [WorkOrderStatus::Blocked->id()],
                        )
                        ->orderByDesc('updated_at')
                        ->limit(25);
                },
            ]))
            ->whereIn('status', $openTicketStatusIds)
            ->orderByDesc('updated_at')
            ->limit(25);

        if ($locationId === null) {
            $query->whereNull('location_id');
        } else {
            $query->where('location_id', $locationId);
        }

        if ($technicianId) {
            $query->whereHas('workOrders', function (Builder $wo) use ($technicianId, $openWorkOrderStatusIds) {
                $wo->whereIn('status', $openWorkOrderStatusIds)
                    ->where('assigned_user_id', $technicianId);
            });
        }

        return $query->get()
            ->map(fn (ServiceTicket $t) => $this->mapServiceTicketRow($t))
            ->filter(function (array $row) use ($technicianId) {
                if (! $technicianId) {
                    return true;
                }

                return count($row['work_orders'] ?? []) > 0;
            });
    }

    /**
     * @param  array<int, int>  $openWorkOrderStatusIds
     * @param  array<int, int>  $openTicketStatusIds
     * @return Collection<int, array<string, mixed>>
     */
    private function loadStandaloneWorkOrdersForLocation(
        ?int $locationId,
        array $openWorkOrderStatusIds,
        array $openTicketStatusIds,
        ?int $technicianId,
    ): Collection {
        $query = WorkOrder::query()
            ->with($this->workOrderRelations())
            ->whereIn('status', $openWorkOrderStatusIds)
            ->where(function (Builder $q) use ($openTicketStatusIds) {
                $q->whereNull('service_ticket_id')
                    ->orWhereHas(
                        'serviceTicket',
                        fn (Builder $st) => $st->whereNotIn('status', $openTicketStatusIds),
                    );
            })
            ->when($technicianId, fn (Builder $q) => $q->where('assigned_user_id', $technicianId))
            ->orderByRaw(
                'CASE WHEN status = ? THEN 0 ELSE 1 END',
                [WorkOrderStatus::Blocked->id()],
            )
            ->orderByDesc('updated_at')
            ->limit(50);

        if ($locationId === null) {
            $query->whereNull('location_id');
        } else {
            $query->where('location_id', $locationId);
        }

        return $query->get()->map(fn (WorkOrder $wo) => $this->mapWorkOrderRow($wo));
    }

    /**
     * @param  Builder<ServiceTicket|WorkOrder>  $query
     * @param  array<int, array<string, mixed>>  $statusOptions
     * @param  array<int, int>  $allowedStatusIds
     * @return array{labels: list<string>, series: list<int>, colors: list<string>}
     */
    private function statusChartPayload($query, string $statusColumn, array $statusOptions, array $allowedStatusIds): array
    {
        $counts = (clone $query)
            ->selectRaw("{$statusColumn}, count(*) as aggregate")
            ->groupBy($statusColumn)
            ->pluck('aggregate', $statusColumn);

        $labels = [];
        $series = [];
        $colors = [];

        foreach ($statusOptions as $option) {
            $id = (int) $option['id'];
            if (! in_array($id, $allowedStatusIds, true)) {
                continue;
            }
            $count = (int) ($counts[$id] ?? $counts[(string) $id] ?? 0);
            if ($count === 0) {
                continue;
            }
            $labels[] = $option['name'];
            $series[] = $count;
            $colors[] = $this->tailwindColorToHex($option['color'] ?? 'gray');
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'colors' => $colors,
        ];
    }

    /**
     * @param  Builder<WorkOrder>  $query
     * @return array{labels: list<string>, series: list<int>, colors: list<string>}
     */
    private function workOrdersHoursVarianceChart(Builder $query): array
    {
        $buckets = [
            'under' => 0,
            'on' => 0,
            'over' => 0,
            'awaiting_actual' => 0,
            'no_estimate' => 0,
        ];

        foreach ((clone $query)->get(['estimated_hours', 'actual_hours']) as $workOrder) {
            $bucket = $this->hoursVarianceBucket($workOrder->estimated_hours, $workOrder->actual_hours);
            $buckets[$bucket]++;
        }

        $definitions = [
            'under' => ['Under estimate', '#22c55e'],
            'on' => ['On estimate', '#3b82f6'],
            'over' => ['Over estimate', '#ef4444'],
            'awaiting_actual' => ['Awaiting time entry', '#eab308'],
            'no_estimate' => ['No estimate set', '#94a3b8'],
        ];

        $labels = [];
        $series = [];
        $colors = [];

        foreach ($definitions as $key => [$label, $color]) {
            $count = $buckets[$key];
            if ($count === 0) {
                continue;
            }
            $labels[] = $label;
            $series[] = $count;
            $colors[] = $color;
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'colors' => $colors,
        ];
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    private function countWorkOrdersByHoursBucket(Builder $query, string $bucket): int
    {
        $count = 0;
        foreach ((clone $query)->get(['estimated_hours', 'actual_hours']) as $workOrder) {
            if ($this->hoursVarianceBucket($workOrder->estimated_hours, $workOrder->actual_hours) === $bucket) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return 'under'|'on'|'over'|'awaiting_actual'|'no_estimate'
     */
    public function hoursVarianceBucket(mixed $estimatedHours, mixed $actualHours): string
    {
        $estimate = $estimatedHours !== null ? (float) $estimatedHours : 0.0;
        if ($estimate <= 0) {
            return 'no_estimate';
        }

        if ($actualHours === null) {
            return 'awaiting_actual';
        }

        $actual = (float) $actualHours;
        if ($actual <= 0) {
            return 'awaiting_actual';
        }

        if ($actual < $estimate) {
            return 'under';
        }

        if ($actual > $estimate) {
            return 'over';
        }

        return 'on';
    }

    private function tailwindColorToHex(string $color): string
    {
        return match ($color) {
            'blue' => '#3b82f6',
            'indigo' => '#6366f1',
            'yellow' => '#eab308',
            'gray' => '#6b7280',
            'red' => '#ef4444',
            'green' => '#22c55e',
            'slate' => '#64748b',
            'orange' => '#f97316',
            'purple' => '#a855f7',
            default => '#6b7280',
        };
    }

    /**
     * @return array<int|string, mixed>
     */
    private function serviceTicketRelations(): array
    {
        return [
            'customer:id,contact_id',
            'customer.contact:id,display_name,first_name,last_name',
            'assetUnit:id,asset_id,serial_number,hin,sku',
            'assetUnit.asset:id,display_name',
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function workOrderRelations(): array
    {
        return [
            'customer:id,contact_id',
            'customer.contact:id,display_name,first_name,last_name',
            'assignedUser:id,display_name,first_name,last_name',
        ];
    }

    private function userDisplayName(User $user): string
    {
        $name = trim((string) ($user->display_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $full = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return $full !== '' ? $full : 'User #'.$user->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapServiceTicketRow(ServiceTicket $t): array
    {
        $statusMeta = $this->statusMeta((int) $t->status, ServiceTicketStatus::options());

        return [
            'id' => $t->id,
            'number' => $t->service_ticket_number,
            'title' => $t->display_name ?? $t->service_ticket_number,
            'status' => $statusMeta['name'],
            'status_id' => $statusMeta['id'],
            'status_bg_class' => $statusMeta['bgClass'],
            'customer_name' => $this->customerDisplayName($t->customer),
            'asset_label' => $t->assetUnit?->display_name,
            'updated_at' => $t->updated_at?->toIso8601String(),
            'work_orders' => $t->relationLoaded('workOrders')
                ? $t->workOrders->map(fn (WorkOrder $wo) => $this->mapWorkOrderRow($wo))->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapWorkOrderRow(WorkOrder $wo): array
    {
        $statusMeta = $this->statusMeta((int) $wo->status, WorkOrderStatus::options());
        $hoursBucket = $this->hoursVarianceBucket($wo->estimated_hours, $wo->actual_hours);

        return [
            'id' => $wo->id,
            'number' => $wo->work_order_number,
            'title' => $wo->display_name,
            'status' => $statusMeta['name'],
            'status_id' => $statusMeta['id'],
            'status_bg_class' => $statusMeta['bgClass'],
            'customer_name' => $this->customerDisplayName($wo->customer),
            'technician_name' => $wo->assignedUser ? $this->userDisplayName($wo->assignedUser) : null,
            'scheduled_start_at' => $wo->scheduled_start_at?->toIso8601String(),
            'updated_at' => $wo->updated_at?->toIso8601String(),
            'estimated_hours' => $wo->estimated_hours !== null ? (float) $wo->estimated_hours : null,
            'actual_hours' => $wo->actual_hours !== null ? (float) $wo->actual_hours : null,
            'hours_variance' => $hoursBucket,
            'hours_variance_label' => $this->hoursVarianceLabel($hoursBucket),
        ];
    }

    private function hoursVarianceLabel(string $bucket): string
    {
        return match ($bucket) {
            'under' => 'Under estimate',
            'on' => 'On estimate',
            'over' => 'Over estimate',
            'awaiting_actual' => 'Awaiting time',
            default => 'No estimate',
        };
    }

    private function customerDisplayName(?Customer $customer): ?string
    {
        if ($customer === null) {
            return null;
        }

        $name = $customer->display_name;
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        $full = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));

        return $full !== '' ? $full : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array{id: int, name: string, bgClass: string}
     */
    private function statusMeta(int $statusId, array $options): array
    {
        $hit = collect($options)->first(fn (array $option): bool => (int) $option['id'] === $statusId);

        return [
            'id' => $statusId,
            'name' => $hit['name'] ?? '—',
            'bgClass' => $hit['bgClass'] ?? 'bg-gray-200 dark:bg-gray-900 dark:text-white',
        ];
    }

    private function safeRoute(string $name, array $parameters = []): ?string
    {
        if (! app('router')->has($name)) {
            return null;
        }

        return route($name, $parameters);
    }
}
