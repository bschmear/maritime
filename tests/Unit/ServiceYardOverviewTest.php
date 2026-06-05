<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\ServiceTicket\Status as ServiceTicketStatus;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use App\Services\ServiceYard\ServiceYardOverviewDataService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ServiceYardOverviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('service_tickets');

        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('service_ticket_number')->nullable();
            $table->unsignedTinyInteger('status')->default(2);
            $table->unsignedBigInteger('location_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->unsignedInteger('work_order_number')->nullable();
            $table->unsignedTinyInteger('status')->default(2);
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('service_ticket_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('service_tickets');

        parent::tearDown();
    }

    public function test_open_status_id_sets_match_yard_overview_filters(): void
    {
        $ticketOpen = [ServiceTicketStatus::Open->id(), ServiceTicketStatus::InProgress->id()];
        $woOpen = [
            WorkOrderStatus::Open->id(),
            WorkOrderStatus::Scheduled->id(),
            WorkOrderStatus::InProgress->id(),
            WorkOrderStatus::Waiting->id(),
            WorkOrderStatus::Blocked->id(),
        ];

        $this->assertSame([2, 3], $ticketOpen);
        $this->assertSame([2, 3, 4, 5, 6], $woOpen);
    }

    public function test_blocked_work_order_on_completed_ticket_is_included_in_standalone_yard_query(): void
    {
        $ticket = ServiceTicket::query()->create([
            'status' => ServiceTicketStatus::Completed->id(),
            'location_id' => 5,
        ]);

        $blocked = WorkOrder::query()->create([
            'status' => WorkOrderStatus::Blocked->id(),
            'location_id' => 5,
            'service_ticket_id' => $ticket->id,
            'work_order_number' => 9001,
        ]);

        WorkOrder::query()->create([
            'status' => WorkOrderStatus::Blocked->id(),
            'location_id' => 5,
            'service_ticket_id' => null,
            'work_order_number' => 9002,
        ]);

        $service = new ServiceYardOverviewDataService;
        $method = new ReflectionMethod(ServiceYardOverviewDataService::class, 'loadStandaloneWorkOrdersForLocation');
        $method->setAccessible(true);

        $openWo = [
            WorkOrderStatus::Open->id(),
            WorkOrderStatus::Scheduled->id(),
            WorkOrderStatus::InProgress->id(),
            WorkOrderStatus::Waiting->id(),
            WorkOrderStatus::Blocked->id(),
        ];
        $openTicket = [
            ServiceTicketStatus::Open->id(),
            ServiceTicketStatus::InProgress->id(),
        ];

        $rows = $method->invoke($service, 5, $openWo, $openTicket, null);
        $ids = collect($rows)->pluck('id')->all();

        $this->assertContains($blocked->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_kanban_status_set_includes_terminal_states(): void
    {
        $kanbanIds = [
            WorkOrderStatus::Open->id(),
            WorkOrderStatus::Scheduled->id(),
            WorkOrderStatus::InProgress->id(),
            WorkOrderStatus::Waiting->id(),
            WorkOrderStatus::Blocked->id(),
            WorkOrderStatus::Completed->id(),
            WorkOrderStatus::Closed->id(),
            WorkOrderStatus::Cancelled->id(),
        ];

        $this->assertContains(8, $kanbanIds);
        $this->assertContains(9, $kanbanIds);
        $this->assertNotContains(1, $kanbanIds);
    }

    public function test_hours_variance_bucket_classification(): void
    {
        $service = new ServiceYardOverviewDataService;

        $this->assertSame('no_estimate', $service->hoursVarianceBucket(null, null));
        $this->assertSame('no_estimate', $service->hoursVarianceBucket(0, 5));
        $this->assertSame('awaiting_actual', $service->hoursVarianceBucket(10, null));
        $this->assertSame('awaiting_actual', $service->hoursVarianceBucket(10, 0));
        $this->assertSame('under', $service->hoursVarianceBucket(10, 4));
        $this->assertSame('on', $service->hoursVarianceBucket(10, 10));
        $this->assertSame('over', $service->hoursVarianceBucket(10, 12));
    }
}
