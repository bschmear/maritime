<?php

namespace App\Services;

use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\ServiceTicketServiceItem\Models\ServiceTicketServiceItem;
use Illuminate\Support\Str;

class ServiceTicketService
{
    /**
     * Create a new service ticket.
     */
    public function create(array $data): ServiceTicket
    {
        $serviceItems = $data['service_items'] ?? [];
        unset($data['service_items']);

        if (empty($data['uuid'])) {
            $data['uuid'] = (string) Str::uuid();
        }

        $ticket = ServiceTicket::create($data);

        if (!empty($serviceItems)) {
            $this->syncServiceItems($ticket, $serviceItems);
        }

        $ticket->recalculateEstimates();

        return $ticket;
    }

    /**
     * Update an existing service ticket.
     */
    public function update(ServiceTicket $ticket, array $data): ServiceTicket
    {
        $serviceItems = $data['service_items'] ?? null;
        unset($data['service_items']);

        $ticket->update($data);

        if ($serviceItems !== null) {
            $this->syncServiceItems($ticket, $serviceItems);
        }

        $ticket->recalculateEstimates();

        return $ticket->fresh();
    }

    /**
     * Delete a service ticket.
     */
    public function delete(ServiceTicket $ticket): void
    {
        $ticket->serviceItems()->delete();
        $ticket->delete();
    }

    /**
     * Sync service items for a ticket (delete + recreate).
     */
    public function syncServiceItems(ServiceTicket $ticket, array $items): void
    {
        // Delete existing items
        $ticket->serviceItems()->delete();

        // Create new ones
        foreach ($items as $index => $itemData) {
            $lineItem = ServiceTicketServiceItem::create([
                'service_ticket_id' => $ticket->id,
                'service_item_id' => $itemData['service_item_id'] ?? null,
                'display_name' => $itemData['display_name'] ?? '',
                'description' => $itemData['description'] ?? '',
                'quantity' => $itemData['quantity'] ?? 1,
                'unit_price' => $itemData['unit_price'] ?? 0,
                'unit_cost' => $itemData['unit_cost'] ?? 0,
                'estimated_hours' => $itemData['estimated_hours'] ?? 0,
                'actual_hours' => $itemData['actual_hours'] ?? 0,
                'billable' => $itemData['billable'] ?? true,
                'warranty' => $itemData['warranty'] ?? false,
                'billing_type' => $itemData['billing_type'] ?? null,
                'sort_order' => $itemData['sort_order'] ?? $index,
            ]);

            $lineItem->recalculate();
        }
    }
}