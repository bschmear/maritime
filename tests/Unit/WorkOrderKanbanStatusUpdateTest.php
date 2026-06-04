<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkOrderKanbanStatusUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('work_orders');

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->unsignedTinyInteger('status')->default(2);
            $table->string('work_order_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('work_orders');

        parent::tearDown();
    }

    public function test_work_order_status_can_move_to_closed_or_cancelled(): void
    {
        $workOrder = WorkOrder::query()->create([
            'status' => WorkOrderStatus::InProgress->id(),
            'work_order_number' => 1001,
        ]);

        $workOrder->update(['status' => WorkOrderStatus::Closed->id()]);
        $this->assertSame(WorkOrderStatus::Closed->id(), (int) $workOrder->fresh()->status);

        $workOrder->update(['status' => WorkOrderStatus::Cancelled->id()]);
        $this->assertSame(WorkOrderStatus::Cancelled->id(), (int) $workOrder->fresh()->status);
    }
}
