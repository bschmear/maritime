<?php

declare(strict_types=1);

namespace App\Domain\WorkOrder\Support;

use App\Enums\ServiceTicket\Status as ServiceTicketStatus;
use App\Enums\WorkOrder\Status as WorkOrderStatus;

final class MapWorkOrderStatusToServiceTicketStatus
{
    public static function map(int $workOrderStatusId): ?int
    {
        return match ($workOrderStatusId) {
            WorkOrderStatus::Draft->id() => ServiceTicketStatus::Draft->id(),
            WorkOrderStatus::Open->id() => ServiceTicketStatus::Open->id(),
            WorkOrderStatus::Scheduled->id() => ServiceTicketStatus::Open->id(),
            WorkOrderStatus::InProgress->id() => ServiceTicketStatus::InProgress->id(),
            WorkOrderStatus::Waiting->id() => ServiceTicketStatus::InProgress->id(),
            WorkOrderStatus::Blocked->id() => ServiceTicketStatus::InProgress->id(),
            WorkOrderStatus::Completed->id() => ServiceTicketStatus::Completed->id(),
            WorkOrderStatus::Closed->id() => ServiceTicketStatus::Closed->id(),
            WorkOrderStatus::Cancelled->id() => ServiceTicketStatus::Cancelled->id(),
            default => null,
        };
    }

    /**
     * @return array<int, int|null>
     */
    public static function all(): array
    {
        $map = [];
        foreach (WorkOrderStatus::cases() as $case) {
            $map[$case->id()] = self::map($case->id());
        }

        return $map;
    }
}
