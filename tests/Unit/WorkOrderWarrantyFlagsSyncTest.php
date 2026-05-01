<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrder\Support\SyncWorkOrderWarrantyFlags;
use App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
use App\Enums\WarrantyClaim\Status;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkOrderWarrantyFlagsSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('warranty_claim_line_items');
        Schema::dropIfExists('warrantyclaims');
        Schema::dropIfExists('work_order_service_items');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('vendors');

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->default('Vendor');
            $table->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('status')->default(2);
            $table->boolean('has_warranty')->default(false);
            $table->boolean('warranty_closed')->default(true);
            $table->timestamps();
        });

        Schema::create('work_order_service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('display_name')->default('Line');
            $table->boolean('warranty')->default(false);
            $table->string('warranty_type')->nullable();
            $table->timestamps();
        });

        Schema::create('warrantyclaims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('claim_number')->nullable();
            $table->string('status', 32)->default('draft');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('warranty_claim_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')->constrained('warrantyclaims')->cascadeOnDelete();
            $table->string('description')->default('x');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('warranty_claim_line_items');
        Schema::dropIfExists('warrantyclaims');
        Schema::dropIfExists('work_order_service_items');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('vendors');

        parent::tearDown();
    }

    public function test_no_manufacturer_lines_marks_flags_false_and_closed(): void
    {
        $wo = WorkOrder::query()->create([
            'status' => WorkOrderStatus::Open->id(),
            'has_warranty' => true,
            'warranty_closed' => false,
        ]);

        (app(SyncWorkOrderWarrantyFlags::class))($wo->fresh());

        $wo->refresh();
        $this->assertFalse($wo->has_warranty);
        $this->assertTrue($wo->warranty_closed);
    }

    public function test_manufacturer_line_without_claim_marks_open(): void
    {
        $wo = WorkOrder::query()->create(['status' => WorkOrderStatus::Open->id()]);
        WorkOrderServiceItem::query()->create([
            'work_order_id' => $wo->id,
            'display_name' => 'Labor',
            'warranty' => true,
            'warranty_type' => WarrantyCoverageType::Manufacturer,
        ]);

        (app(SyncWorkOrderWarrantyFlags::class))($wo->fresh(['serviceItems']));

        $wo->refresh();
        $this->assertTrue($wo->has_warranty);
        $this->assertFalse($wo->warranty_closed);
    }

    public function test_draft_claim_keeps_not_closed(): void
    {
        $vendorId = (int) DB::table('vendors')->insertGetId([
            'display_name' => 'Acme',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wo = WorkOrder::query()->create(['status' => WorkOrderStatus::Open->id()]);
        WorkOrderServiceItem::query()->create([
            'work_order_id' => $wo->id,
            'display_name' => 'Labor',
            'warranty' => true,
            'warranty_type' => WarrantyCoverageType::Manufacturer,
        ]);

        WarrantyClaim::query()->create([
            'vendor_id' => $vendorId,
            'work_order_id' => $wo->id,
            'status' => Status::Draft->value,
            'total_amount' => 0,
        ]);

        (app(SyncWorkOrderWarrantyFlags::class))($wo->fresh(['serviceItems']));

        $wo->refresh();
        $this->assertTrue($wo->has_warranty);
        $this->assertFalse($wo->warranty_closed);
    }

    public function test_paid_claim_marks_closed(): void
    {
        $vendorId = (int) DB::table('vendors')->insertGetId([
            'display_name' => 'Acme',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wo = WorkOrder::query()->create(['status' => WorkOrderStatus::Open->id()]);
        WorkOrderServiceItem::query()->create([
            'work_order_id' => $wo->id,
            'display_name' => 'Labor',
            'warranty' => true,
            'warranty_type' => WarrantyCoverageType::Manufacturer,
        ]);

        WarrantyClaim::query()->create([
            'vendor_id' => $vendorId,
            'work_order_id' => $wo->id,
            'status' => Status::Paid->value,
            'total_amount' => 100,
        ]);

        (app(SyncWorkOrderWarrantyFlags::class))($wo->fresh(['serviceItems']));

        $wo->refresh();
        $this->assertTrue($wo->has_warranty);
        $this->assertTrue($wo->warranty_closed);
    }
}
