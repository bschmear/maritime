<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Customer\Models\Customer;
use App\Domain\Location\Models\Location;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\ServiceTicket\Status as ServiceTicketStatus;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceYardController extends Controller
{
    /**
     * Status IDs shown on the yard overview lists and charts (open / in-flight work).
     */
    private function openTicketStatusIds(): array
    {
        return [
            ServiceTicketStatus::Open->id(),
            ServiceTicketStatus::InProgress->id(),
        ];
    }

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

    /**
     * Open service tickets and work orders grouped by depart location.
     */
    public function index(Request $request)
    {
        $openTicketStatusIds = $this->openTicketStatusIds();
        $openWorkOrderStatusIds = $this->openWorkOrderStatusIds();

        $ticketStatusOptions = ServiceTicketStatus::options();
        $workOrderStatusOptions = WorkOrderStatus::options();

        $locations = Location::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        $sections = [];

        foreach ($locations as $location) {
            $serviceTickets = $this->loadTicketsForLocation($location->id, $openTicketStatusIds, $openWorkOrderStatusIds);
            $standaloneWorkOrders = $this->loadStandaloneWorkOrdersForLocation(
                $location->id,
                $openWorkOrderStatusIds,
                $openTicketStatusIds,
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

        $unassignedTickets = $this->loadTicketsForLocation(null, $openTicketStatusIds, $openWorkOrderStatusIds);
        $unassignedStandalone = $this->loadStandaloneWorkOrdersForLocation(
            null,
            $openWorkOrderStatusIds,
            $openTicketStatusIds,
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

        return Inertia::render('Tenant/ServiceYard/Index', [
            'locationSections' => $sections,
            'enumOptions' => [
                'App\\Enums\\ServiceTicket\\Status' => $ticketStatusOptions,
                'App\\Enums\\WorkOrder\\Status' => $workOrderStatusOptions,
            ],
            'chartData' => [
                'service_tickets_by_status' => $this->statusChartPayload(
                    ServiceTicket::query()
                        ->whereIn('status', $openTicketStatusIds),
                    'status',
                    $ticketStatusOptions,
                    $openTicketStatusIds,
                ),
                'work_orders_by_status' => $this->statusChartPayload(
                    WorkOrder::query()
                        ->whereIn('status', $openWorkOrderStatusIds),
                    'status',
                    $workOrderStatusOptions,
                    $openWorkOrderStatusIds,
                ),
            ],
        ]);
    }

    /**
     * @param  array<int, int>  $openTicketStatusIds
     * @param  array<int, int>  $openWorkOrderStatusIds
     */
    private function loadTicketsForLocation(
        ?int $locationId,
        array $openTicketStatusIds,
        array $openWorkOrderStatusIds,
    ) {
        $query = ServiceTicket::query()
            ->with(array_merge($this->serviceTicketRelations(), [
                'workOrders' => function ($q) use ($openWorkOrderStatusIds) {
                    $q->with($this->workOrderRelations())
                        ->whereIn('status', $openWorkOrderStatusIds)
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

        return $query->get()->map(fn (ServiceTicket $t) => $this->mapServiceTicketRow($t));
    }

    /**
     * Work orders listed outside an open service ticket row (no ticket, or ticket not open).
     *
     * @param  array<int, int>  $openWorkOrderStatusIds
     * @param  array<int, int>  $openTicketStatusIds
     */
    private function loadStandaloneWorkOrdersForLocation(
        ?int $locationId,
        array $openWorkOrderStatusIds,
        array $openTicketStatusIds,
    ) {
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
        $tech = $wo->assignedUser;
        $techName = $tech?->display_name ?: trim(($tech?->first_name ?? '').' '.($tech?->last_name ?? ''));

        return [
            'id' => $wo->id,
            'number' => $wo->work_order_number,
            'title' => $wo->display_name,
            'status' => $statusMeta['name'],
            'status_id' => $statusMeta['id'],
            'status_bg_class' => $statusMeta['bgClass'],
            'customer_name' => $this->customerDisplayName($wo->customer),
            'technician_name' => $techName ?: null,
            'scheduled_start_at' => $wo->scheduled_start_at?->toIso8601String(),
            'updated_at' => $wo->updated_at?->toIso8601String(),
        ];
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
}
