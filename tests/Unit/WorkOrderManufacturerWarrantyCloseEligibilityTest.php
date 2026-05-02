<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WarrantyClaim\Support\WorkOrderManufacturerWarrantyCloseEligibility;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
use App\Enums\WarrantyClaim\Status;
use App\Enums\WorkOrder\Status as WorkOrderStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkOrderManufacturerWarrantyCloseEligibilityTest extends TestCase
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
            $table->boolean('inactive')->default(false);
            $table->timestamps();
        });

        Schema::create('warrantyclaims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sequence')->unique();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->unsignedBigInteger('subsidiary_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('claim_number')->nullable();
            $table->string('status', 32)->default('draft');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_claim_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')->constrained('warrantyclaims')->cascadeOnDelete();
            $table->unsignedBigInteger('work_order_service_item_id')->nullable();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->nullable();
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

    public function test_no_block_when_no_manufacturer_warranty_lines(): void
    {
        $wo = WorkOrder::query()->create(['status' => WorkOrderStatus::Open->id()]);
        $eligibility = new WorkOrderManufacturerWarrantyCloseEligibility;

        $this->assertNull($eligibility->reasonIfBlocked($wo, null));
    }

    public function test_blocked_when_manufacturer_lines_and_no_claim(): void
    {
        $wo = WorkOrder::query()->create(['status' => WorkOrderStatus::Open->id()]);
        WorkOrderServiceItem::query()->create([
            'work_order_id' => $wo->id,
            'display_name' => 'Labor',
            'warranty' => true,
            'warranty_type' => WarrantyCoverageType::Manufacturer,
        ]);

        $eligibility = new WorkOrderManufacturerWarrantyCloseEligibility;
        $this->assertNotNull($eligibility->reasonIfBlocked($wo, null));
    }

    public function test_allowed_when_claim_paid(): void
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

        $eligibility = new WorkOrderManufacturerWarrantyCloseEligibility;
        $this->assertNull($eligibility->reasonIfBlocked($wo, null));
    }

    public function test_blocked_when_claim_draft(): void
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
            'total_amount' => 100,
        ]);

        $eligibility = new WorkOrderManufacturerWarrantyCloseEligibility;
        $this->assertNotNull($eligibility->reasonIfBlocked($wo, null));
    }

    public function test_incoming_service_items_empty_clears_manufacturer_warranty_for_check(): void
    {
        $wo = WorkOrder::query()->create(['status' => WorkOrderStatus::Open->id()]);
        WorkOrderServiceItem::query()->create([
            'work_order_id' => $wo->id,
            'display_name' => 'Labor',
            'warranty' => true,
            'warranty_type' => WarrantyCoverageType::Manufacturer,
        ]);

        $eligibility = new WorkOrderManufacturerWarrantyCloseEligibility;
        $this->assertNull($eligibility->reasonIfBlocked($wo, []));
    }
}
