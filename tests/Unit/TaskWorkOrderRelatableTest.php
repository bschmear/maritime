<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Task\Models\Task;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TaskWorkOrderRelatableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('tasks');
        Schema::dropIfExists('work_orders');

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->unsignedTinyInteger('status')->default(2);
            $table->unsignedInteger('work_order_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->unsignedTinyInteger('priority_id')->default(2);
            $table->boolean('completed')->default(false);
            $table->nullableMorphs('relatable');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('work_orders');

        parent::tearDown();
    }

    public function test_task_can_morph_to_work_order(): void
    {
        $workOrder = WorkOrder::query()->create([
            'status' => WorkOrderStatus::Open->id(),
        ]);

        $task = Task::query()->create([
            'display_name' => 'Inspect hull',
            'status_id' => 1,
            'priority_id' => 2,
            'relatable_type' => WorkOrder::class,
            'relatable_id' => $workOrder->id,
        ]);

        $workOrder->load('tasks');

        $this->assertCount(1, $workOrder->tasks);
        $this->assertSame($task->id, $workOrder->tasks->first()->id);
        $this->assertSame(WorkOrder::class, $task->relatable_type);
    }

    public function test_task_schema_includes_work_order_morph_option(): void
    {
        $path = app_path('Domain/Task/Schema/fields.json');
        $schema = json_decode((string) file_get_contents($path), true);
        $fields = $schema['fields'] ?? $schema;
        $types = $fields['relatable_type']['morphable_types'] ?? [];
        $values = array_column($types, 'value');

        $this->assertContains('App\\Domain\\WorkOrder\\Models\\WorkOrder', $values);
    }
}
