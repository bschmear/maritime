<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Enums\WarrantyClaim\Status;
use App\Models\User as WebUser;
use App\Policies\WarrantyClaimPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarrantyClaimPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('contact_vendor');
        Schema::dropIfExists('warrantyclaims');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('vendors');

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->default('Vendor');
            $table->unsignedBigInteger('primary_contact_id')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('type')->default('1');
            $table->string('status')->default('1');
            $table->unsignedTinyInteger('stage_id')->default(1);
            $table->boolean('inactive')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contact_vendor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('contact_vendor');
        Schema::dropIfExists('warrantyclaims');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('vendors');

        parent::tearDown();
    }

    public function test_vendor_respond_allows_when_contact_linked_to_claim_vendor(): void
    {
        $vendorId = (int) Vendor::query()->create(['display_name' => 'Acme Mfg'])->id;
        $contact = Contact::query()->create([
            'email' => 'rep@example.com',
            'display_name' => 'Rep',
            'first_name' => 'R',
            'last_name' => 'E',
        ]);
        $contact->vendors()->attach($vendorId, ['is_primary' => true]);

        $claim = WarrantyClaim::query()->create([
            'vendor_id' => $vendorId,
            'status' => Status::Submitted->value,
            'total_amount' => 10,
        ]);

        $policy = new WarrantyClaimPolicy;

        $this->assertTrue($policy->vendorRespond($contact, $claim));
    }

    public function test_vendor_respond_denies_when_vendor_mismatch(): void
    {
        $vendorA = (int) Vendor::query()->create(['display_name' => 'A'])->id;
        $vendorB = (int) Vendor::query()->create(['display_name' => 'B'])->id;
        $contact = Contact::query()->create([
            'email' => 'rep@example.com',
            'display_name' => 'Rep',
            'first_name' => 'R',
            'last_name' => 'E',
        ]);
        $contact->vendors()->attach($vendorA, ['is_primary' => true]);

        $claim = WarrantyClaim::query()->create([
            'vendor_id' => $vendorB,
            'status' => Status::Submitted->value,
            'total_amount' => 10,
        ]);

        $policy = new WarrantyClaimPolicy;

        $this->assertFalse($policy->vendorRespond($contact, $claim));
    }

    public function test_send_to_vendor_allows_authenticated_web_user(): void
    {
        $webUser = new WebUser;
        $webUser->id = 1;
        $webUser->email = 'staff@example.com';

        $claim = WarrantyClaim::query()->create([
            'vendor_id' => null,
            'status' => Status::Submitted->value,
            'total_amount' => 1,
        ]);

        $policy = new WarrantyClaimPolicy;

        $this->assertTrue($policy->sendToVendor($webUser, $claim));
    }

    public function test_gate_vendor_respond_authorizes_for_user_contact(): void
    {
        $vendorId = (int) Vendor::query()->create(['display_name' => 'Acme Mfg'])->id;
        $contact = Contact::query()->create([
            'email' => 'rep@example.com',
            'display_name' => 'Rep',
            'first_name' => 'R',
            'last_name' => 'E',
        ]);
        $contact->vendors()->attach($vendorId, ['is_primary' => true]);

        $claim = WarrantyClaim::query()->create([
            'vendor_id' => $vendorId,
            'status' => Status::Submitted->value,
            'total_amount' => 10,
        ]);

        $this->assertTrue(Gate::forUser($contact)->allows('vendorRespond', $claim));
    }
}
