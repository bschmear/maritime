<?php

declare(strict_types=1);

namespace App\Domain\WorkOrder\Support;

use App\Domain\ServiceTicket\Actions\UpdateServiceTicket;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WorkOrder\Models\WorkOrder;

final class SyncWorkOrderStatusToServiceTicket
{
    public function __invoke(WorkOrder $workOrder, int $workOrderStatusId): void
    {
        if (! $workOrder->service_ticket_id) {
            return;
        }

        $mappedStatus = MapWorkOrderStatusToServiceTicketStatus::map($workOrderStatusId);
        if ($mappedStatus === null) {
            return;
        }

        $ticket = ServiceTicket::find($workOrder->service_ticket_id);
        if (! $ticket || (int) $ticket->status === $mappedStatus) {
            return;
        }

        app(UpdateServiceTicket::class)($ticket->id, ['status' => $mappedStatus]);
    }
}
