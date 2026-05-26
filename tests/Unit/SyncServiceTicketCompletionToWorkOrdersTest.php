<?php

namespace Tests\Unit;

use App\Domain\ServiceTicket\Support\SyncServiceTicketCompletionToWorkOrders;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use PHPUnit\Framework\TestCase;

class SyncServiceTicketCompletionToWorkOrdersTest extends TestCase
{
    public function test_open_work_order_status_excludes_terminal_statuses(): void
    {
        $this->assertTrue(SyncServiceTicketCompletionToWorkOrders::isOpenWorkOrderStatus(WorkOrderStatus::Open->id()));
        $this->assertTrue(SyncServiceTicketCompletionToWorkOrders::isOpenWorkOrderStatus(WorkOrderStatus::InProgress->id()));
        $this->assertFalse(SyncServiceTicketCompletionToWorkOrders::isOpenWorkOrderStatus(WorkOrderStatus::Completed->id()));
        $this->assertFalse(SyncServiceTicketCompletionToWorkOrders::isOpenWorkOrderStatus(WorkOrderStatus::Closed->id()));
        $this->assertFalse(SyncServiceTicketCompletionToWorkOrders::isOpenWorkOrderStatus(WorkOrderStatus::Cancelled->id()));
    }
}
