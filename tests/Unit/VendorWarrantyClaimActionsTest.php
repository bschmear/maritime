<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\User\Models\User;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\WarrantyClaim\Actions\VendorApproveWarrantyClaim;
use App\Domain\WarrantyClaim\Actions\VendorRejectWarrantyClaim;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Enums\WarrantyClaim\Status;
use App\Mail\WarrantyClaimVendorApprovedCreator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorWarrantyClaimActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('warranty_claim_line_items');
        Schema::dropIfExists('warrantyclaims');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('users');
        Schema::dropIfExists('account_settings');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->default('User');
            $table->string('first_name')->default('U');
            $table->string('last_name')->default('Ser');
            $table->string('email')->unique();
            $table->unsignedBigInteger('current_role')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->default('Vendor');
            $table->unsignedBigInteger('primary_contact_id')->nullable();
            $table->timestamps();
        });

        Schema::create('warrantyclaims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sequence')->unique();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('subsidiary_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('claim_number')->nullable();
            $table->string('status', 32)->default('draft');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('approved_by_vendor')->default(false);
            $table->timestamp('vendor_approved_at')->nullable();
            $table->unsignedBigInteger('vendor_approved_by_contact_id')->nullable();
            $table->text('vendor_notes')->nullable();
            $table->timestamp('vendor_rejected_at')->nullable();
            $table->unsignedBigInteger('vendor_rejected_by_contact_id')->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_claim_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')->constrained('warrantyclaims')->cascadeOnDelete();
            $table->unsignedBigInteger('work_order_service_item_id')->nullable();
            $table->string('description')->default('Line');
            $table->string('cost_type', 32)->default('quantity');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('cost', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('account_settings', function (Blueprint $table) {
            $table->id();
            $table->string('timezone')->default('UTC');
            $table->string('logo_file')->nullable();
            $table->string('logo_file_extension', 10)->nullable();
            $table->integer('logo_file_size')->default(0);
            $table->string('brand_color')->nullable();
            $table->string('date_format')->default('Y-m-d');
            $table->string('time_format')->default('H:i');
            $table->string('currency')->default('USD');
            $table->boolean('week_starts_on_monday')->default(false);
            $table->boolean('auto_assign_work_orders')->default(false);
            $table->json('settings')->nullable();
            $table->integer('workday_hours')->default(6);
            $table->string('start_time')->default('08:00:00');
            $table->boolean('allow_overlap')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('warranty_claim_line_items');
        Schema::dropIfExists('warrantyclaims');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('users');
        Schema::dropIfExists('account_settings');

        parent::tearDown();
    }

    public function test_vendor_approve_sets_audit_fields_and_queues_creator_mail(): void
    {
        Mail::fake();

        $vendorId = (int) Vendor::query()->create(['display_name' => 'Mfg'])->id;
        $creator = User::query()->create([
            'display_name' => 'Creator',
            'first_name' => 'C',
            'last_name' => 'R',
            'email' => 'creator@example.com',
            'current_role' => null,
        ]);

        $claim = WarrantyClaim::query()->create([
            'vendor_id' => $vendorId,
            'status' => Status::Submitted->value,
            'total_amount' => 25,
            'created_by_user_id' => $creator->id,
        ]);

        $action = new VendorApproveWarrantyClaim;
        $result = ($action)($claim, 99);

        $this->assertTrue($result['success'] ?? false);

        $claim->refresh();
        $this->assertSame(Status::Approved, $claim->status);
        $this->assertTrue((bool) $claim->approved_by_vendor);
        $this->assertSame(99, (int) $claim->vendor_approved_by_contact_id);
        $this->assertNotNull($claim->vendor_approved_at);

        Mail::assertQueued(WarrantyClaimVendorApprovedCreator::class, function (WarrantyClaimVendorApprovedCreator $mailable) use ($creator) {
            return (int) $mailable->creator->id === (int) $creator->id;
        });
    }

    public function test_vendor_approve_skips_mail_when_no_creator(): void
    {
        Mail::fake();

        $vendorId = (int) Vendor::query()->create(['display_name' => 'Mfg'])->id;

        $claim = WarrantyClaim::query()->create([
            'vendor_id' => $vendorId,
            'status' => Status::Submitted->value,
            'total_amount' => 25,
            'created_by_user_id' => null,
        ]);

        $action = new VendorApproveWarrantyClaim;
        $result = ($action)($claim, 5);

        $this->assertTrue($result['success'] ?? false);
        Mail::assertNothingQueued();
    }

    public function test_vendor_reject_sets_status_and_vendor_audit(): void
    {
        $vendorId = (int) Vendor::query()->create(['display_name' => 'Mfg'])->id;

        $claim = WarrantyClaim::query()->create([
            'vendor_id' => $vendorId,
            'status' => Status::Submitted->value,
            'total_amount' => 10,
        ]);

        $action = new VendorRejectWarrantyClaim;
        $result = ($action)($claim, 12, [
            'rejection_reason' => 'Not covered',
            'vendor_notes' => 'See policy page 4',
        ]);

        $this->assertTrue($result['success'] ?? false);

        $claim->refresh();
        $this->assertSame(Status::Rejected, $claim->status);
        $this->assertSame('Not covered', $claim->rejection_reason);
        $this->assertSame(12, (int) $claim->vendor_rejected_by_contact_id);
        $this->assertNotNull($claim->vendor_rejected_at);
        $this->assertStringContainsString('See policy page 4', (string) $claim->vendor_notes);
    }
}
