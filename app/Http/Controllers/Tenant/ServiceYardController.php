<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Customer\Models\Customer;
use App\Domain\Location\Models\Location;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\ServiceTicket\Status as ServiceTicketStatus;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceYardController extends Controller
{
    /**
     * Open service tickets and work orders grouped by depart location.
     */
    public function index(Request $request)
    {
        $openTicketStatusIds = [
            ServiceTicketStatus::Open->id(),
            ServiceTicketStatus::InProgress->id(),
        ];

        $openWorkOrderStatusIds = [
            WorkOrderStatus::Open->id(),
            WorkOrderStatus::Scheduled->id(),
            WorkOrderStatus::InProgress->id(),
            WorkOrderStatus::Waiting->id(),
            WorkOrderStatus::Blocked->id(),
        ];

        $locations = Location::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        $sections = [];

        foreach ($locations as $location) {
            $serviceTickets = ServiceTicket::query()
                ->with($this->serviceTicketRelations())
                ->where('location_id', $location->id)
                ->whereIn('status', $openTicketStatusIds)
                ->orderByDesc('updated_at')
                ->limit(25)
                ->get()
                ->map(fn (ServiceTicket $t) => $this->mapServiceTicketRow($t));

            $workOrders = WorkOrder::query()
                ->with($this->workOrderRelations())
                ->where('location_id', $location->id)
                ->whereIn('status', $openWorkOrderStatusIds)
                ->orderByDesc('updated_at')
                ->limit(25)
                ->get()
                ->map(fn (WorkOrder $wo) => $this->mapWorkOrderRow($wo));

            if ($serviceTickets->isEmpty() && $workOrders->isEmpty()) {
                continue;
            }

            $sections[] = [
                'location' => [
                    'id' => $location->id,
                    'display_name' => $location->display_name,
                ],
                'service_tickets' => $serviceTickets->values()->all(),
                'work_orders' => $workOrders->values()->all(),
            ];
        }

        $unassignedTickets = ServiceTicket::query()
            ->with($this->serviceTicketRelations())
            ->whereNull('location_id')
            ->whereIn('status', $openTicketStatusIds)
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->map(fn (ServiceTicket $t) => $this->mapServiceTicketRow($t));

        $unassignedWorkOrders = WorkOrder::query()
            ->with($this->workOrderRelations())
            ->whereNull('location_id')
            ->whereIn('status', $openWorkOrderStatusIds)
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->map(fn (WorkOrder $wo) => $this->mapWorkOrderRow($wo));

        if ($unassignedTickets->isNotEmpty() || $unassignedWorkOrders->isNotEmpty()) {
            $sections[] = [
                'location' => [
                    'id' => null,
                    'display_name' => 'No location assigned',
                ],
                'service_tickets' => $unassignedTickets->values()->all(),
                'work_orders' => $unassignedWorkOrders->values()->all(),
            ];
        }

        return Inertia::render('Tenant/ServiceYard/Index', [
            'locationSections' => $sections,
        ]);
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
        $status = ServiceTicketStatus::options();
        $statusLabel = collect($status)->firstWhere('id', (int) $t->status)['name'] ?? '—';

        return [
            'id' => $t->id,
            'number' => $t->service_ticket_number,
            'title' => $t->display_name ?? $t->service_ticket_number,
            'status' => $statusLabel,
            'customer_name' => $this->customerDisplayName($t->customer),
            'asset_label' => $t->assetUnit?->display_name,
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapWorkOrderRow(WorkOrder $wo): array
    {
        $statusLabel = collect(WorkOrderStatus::options())->firstWhere('id', (int) $wo->status)['name'] ?? '—';
        $tech = $wo->assignedUser;
        $techName = $tech?->display_name ?: trim(($tech?->first_name ?? '').' '.($tech?->last_name ?? ''));

        return [
            'id' => $wo->id,
            'number' => $wo->work_order_number,
            'title' => $wo->display_name,
            'status' => $statusLabel,
            'customer_name' => $this->customerDisplayName($wo->customer),
            'technician_name' => $techName ?: null,
            'scheduled_start_at' => $wo->scheduled_start_at?->toIso8601String(),
            'updated_at' => $wo->updated_at?->toIso8601String(),
        ];
    }
}
