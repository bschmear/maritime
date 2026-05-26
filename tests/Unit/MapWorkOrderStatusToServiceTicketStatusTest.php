<?php

namespace Tests\Unit;

use App\Domain\WorkOrder\Support\MapWorkOrderStatusToServiceTicketStatus;
use App\Enums\ServiceTicket\Status as ServiceTicketStatus;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use PHPUnit\Framework\TestCase;

class MapWorkOrderStatusToServiceTicketStatusTest extends TestCase
{
    public function test_maps_direct_statuses(): void
    {
        $this->assertSame(
            ServiceTicketStatus::Open->id(),
            MapWorkOrderStatusToServiceTicketStatus::map(WorkOrderStatus::Open->id())
        );
        $this->assertSame(
            ServiceTicketStatus::Completed->id(),
            MapWorkOrderStatusToServiceTicketStatus::map(WorkOrderStatus::Completed->id())
        );
    }

    public function test_maps_intermediate_work_order_statuses_to_in_progress(): void
    {
        $this->assertSame(
            ServiceTicketStatus::InProgress->id(),
            MapWorkOrderStatusToServiceTicketStatus::map(WorkOrderStatus::Waiting->id())
        );
        $this->assertSame(
            ServiceTicketStatus::Open->id(),
            MapWorkOrderStatusToServiceTicketStatus::map(WorkOrderStatus::Scheduled->id())
        );
    }

    public function test_all_returns_entry_for_every_work_order_status(): void
    {
        $map = MapWorkOrderStatusToServiceTicketStatus::all();

        foreach (WorkOrderStatus::cases() as $case) {
            $this->assertArrayHasKey($case->id(), $map);
            $this->assertNotNull($map[$case->id()]);
        }
    }
}
