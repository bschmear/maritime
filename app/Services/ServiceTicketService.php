<?php

namespace App\Services;

use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\ServiceTicketServiceItem\Models\ServiceTicketServiceItem;
use App\Domain\ServiceTicket\Actions\CreateServiceTicket;
use App\Domain\ServiceTicket\Actions\UpdateServiceTicket;
use App\Domain\ServiceTicket\Actions\DeleteServiceTicket;
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

        $result = (new CreateServiceTicket())($data);

        if (!$result['success']) {
            throw new \Exception($result['message'] ?? 'Failed to create service ticket');
        }

        $ticket = $result['record'];

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

        $result = (new UpdateServiceTicket())($ticket->id, $data);

        if (!$result['success']) {
            throw new \Exception($result['message'] ?? 'Failed to update service ticket');
        }

        $ticket = $result['record'];

        if ($serviceItems !== null) {
            $this->syncServiceItems($ticket, $serviceItems);
        }

        $ticket->recalculateEstimates();

        return $ticket;
    }

    /**
     * Delete a service ticket.
     */
    public function delete(ServiceTicket $ticket): void
    {
        $result = (new DeleteServiceTicket())($ticket->id);

        if (!$result['success']) {
            throw new \Exception($result['message'] ?? 'Failed to delete service ticket');
        }
    }

    /**
     * Sync service items for a ticket (delete + recreate).
     */
    protected function syncServiceItems(ServiceTicket $ticket, array $items): void
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
                'billable' => $itemData['billable'] ?? true,
                'warranty' => $itemData['warranty'] ?? false,
                'billing_type' => $itemData['billing_type'] ?? null,
                'sort_order' => $itemData['sort_order'] ?? $index,
            ]);

            $lineItem->recalculate();
        }
    }
}