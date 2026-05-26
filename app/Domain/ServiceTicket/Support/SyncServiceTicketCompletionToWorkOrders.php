<?php

declare(strict_types=1);

namespace App\Domain\ServiceTicket\Support;

use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WorkOrder\Actions\UpdateWorkOrder;
use App\Enums\WorkOrder\Status as WorkOrderStatus;

final class SyncServiceTicketCompletionToWorkOrders
{
    public static function isOpenWorkOrderStatus(int $statusId): bool
    {
        return ! in_array($statusId, [
            WorkOrderStatus::Completed->id(),
            WorkOrderStatus::Closed->id(),
            WorkOrderStatus::Cancelled->id(),
        ], true);
    }

    public function __invoke(ServiceTicket $ticket): void
    {
        $ticket->loadMissing('workOrders');

        $completedStatus = WorkOrderStatus::Completed->id();

        foreach ($ticket->workOrders as $workOrder) {
            if (! self::isOpenWorkOrderStatus((int) $workOrder->status)) {
                continue;
            }

            app(UpdateWorkOrder::class)($workOrder->id, ['status' => $completedStatus]);
        }
    }
}
