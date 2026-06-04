<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\WorkOrder\Actions\LogWorkOrderLineItemTime;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LogWorkOrderLineItemTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('work_order_service_items');
        Schema::dropIfExists('work_orders');

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->unsignedInteger('work_order_number')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->nullable();
            $table->decimal('labor_cost', 10, 2)->default(0);
            $table->decimal('parts_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->decimal('tax_rate', 8, 2)->nullable();
            $table->decimal('estimated_tax', 10, 2)->nullable();
            $table->boolean('has_warranty')->default(false);
            $table->boolean('warranty_closed')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('work_order_service_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->string('display_name')->nullable();
            $table->unsignedTinyInteger('billing_type')->default(1);
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->nullable();
            $table->boolean('billable')->default(true);
            $table->boolean('warranty')->default(false);
            $table->string('billable_to')->default('customer');
            $table->boolean('inactive')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('work_order_service_items');
        Schema::dropIfExists('work_orders');

        parent::tearDown();
    }

    public function test_adds_hours_to_line_item_and_work_order_totals(): void
    {
        $workOrder = WorkOrder::query()->create([
            'work_order_number' => 1001,
            'tax_rate' => 0,
        ]);

        $line = WorkOrderServiceItem::query()->create([
            'work_order_id' => $workOrder->id,
            'display_name' => 'Engine diag',
            'billing_type' => 1,
            'estimated_hours' => 2,
            'actual_hours' => 0.5,
            'unit_price' => 100,
            'unit_cost' => 50,
        ]);

        $result = app(LogWorkOrderLineItemTime::class)($workOrder->id, [
            'line_item_id' => $line->id,
            'hours' => 1.25,
        ]);

        $line->refresh();
        $workOrder->refresh();

        $this->assertSame(1.75, (float) $line->actual_hours);
        $this->assertSame(1.75, (float) $workOrder->actual_hours);
        $this->assertSame(2.0, (float) $result['work_order']['estimated_hours']);
        $this->assertSame(1.75, (float) $result['work_order']['actual_hours']);
    }

    public function test_sets_line_item_actual_hours_to_exact_total(): void
    {
        $workOrder = WorkOrder::query()->create([
            'work_order_number' => 1002,
            'tax_rate' => 0,
        ]);

        $line = WorkOrderServiceItem::query()->create([
            'work_order_id' => $workOrder->id,
            'display_name' => 'Hull repair',
            'billing_type' => 1,
            'estimated_hours' => 4,
            'actual_hours' => 6,
            'unit_price' => 100,
            'unit_cost' => 50,
        ]);

        $result = app(LogWorkOrderLineItemTime::class)($workOrder->id, [
            'line_item_id' => $line->id,
            'mode' => 'set',
            'actual_hours' => 2.5,
        ]);

        $line->refresh();
        $workOrder->refresh();

        $this->assertSame(2.5, (float) $line->actual_hours);
        $this->assertSame(2.5, (float) $workOrder->actual_hours);
        $this->assertSame(2.5, (float) $result['line_item']['actual_hours']);
    }
}
