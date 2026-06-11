<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Communication\Models\Communication;
use App\Domain\Contact\Models\Contact;
use App\Domain\User\Models\User;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WarrantyClaim\Support\LogWarrantyClaimVendorEmailCommunication;
use App\Enums\Communication\CommunicationType;
use App\Models\AccountSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogWarrantyClaimVendorEmailCommunicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        $schema = Schema::connection('tenant');

        $schema->dropIfExists('communications');
        $schema->dropIfExists('warrantyclaims');
        $schema->dropIfExists('contacts');
        $schema->dropIfExists('vendors');
        $schema->dropIfExists('users');
        $schema->dropIfExists('account_settings');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->default('Staff');
            $table->string('first_name')->default('S');
            $table->string('last_name')->default('T');
            $table->string('email')->unique();
            $table->unsignedBigInteger('current_role')->nullable();
            $table->timestamps();
        });

        $schema->create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->default('Acme Mfg');
            $table->unsignedBigInteger('primary_contact_id')->nullable();
            $table->timestamps();
        });

        $schema->create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $schema->create('warrantyclaims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sequence')->unique();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('status', 32)->default('submitted');
            $table->timestamps();
        });

        $schema->create('communications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->morphs('communicable');
            $table->unsignedTinyInteger('communication_type_id')->index();
            $table->enum('direction', ['inbound', 'outbound'])->nullable();
            $table->string('subject')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('needs_follow_up')->default(false);
            $table->boolean('is_private')->default(false);
            $table->unsignedTinyInteger('status_id')->default(1)->index();
            $table->unsignedTinyInteger('channel_id')->nullable()->index();
            $table->unsignedTinyInteger('priority_id')->default(2)->index();
            $table->json('tags')->nullable();
            $table->unsignedTinyInteger('outcome_id')->nullable()->index();
            $table->timestamp('next_action_at')->nullable();
            $table->unsignedTinyInteger('next_action_type_id')->nullable()->index();
            $table->string('calendar_id')->nullable();
            $table->string('event_id')->nullable();
            $table->timestamp('date_contacted')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('account_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('communications');
        $schema->dropIfExists('warrantyclaims');
        $schema->dropIfExists('contacts');
        $schema->dropIfExists('vendors');
        $schema->dropIfExists('users');
        $schema->dropIfExists('account_settings');

        parent::tearDown();
    }

    public function test_logs_outbound_email_on_contact_and_vendor(): void
    {
        $user = User::query()->create([
            'display_name' => 'Casey Staff',
            'first_name' => 'Casey',
            'last_name' => 'Staff',
            'email' => 'staff-'.Str::random(6).'@example.test',
        ]);

        $vendor = Vendor::query()->create(['display_name' => 'Acme Mfg']);
        $contact = Contact::query()->create([
            'display_name' => 'Pat Vendor',
            'email' => 'pat@acme.test',
        ]);

        $claim = WarrantyClaim::query()->create([
            'uuid' => (string) Str::uuid(),
            'sequence' => 5001,
            'vendor_id' => $vendor->id,
            'created_by_user_id' => $user->id,
            'status' => 'submitted',
        ]);

        $account = new AccountSettings(['name' => 'Helmful Test']);

        app(LogWarrantyClaimVendorEmailCommunication::class)(
            $claim,
            $contact,
            'pat@acme.test',
            $account,
            'https://tenant.test/warranty-claims/review/'.$claim->uuid,
            'https://tenant.test/vendor/portal/login',
            $user,
        );

        $this->assertSame(2, Communication::query()->count());

        $contactLog = Communication::query()
            ->where('communicable_type', Contact::class)
            ->where('communicable_id', $contact->id)
            ->first();

        $this->assertNotNull($contactLog);
        $this->assertSame(CommunicationType::Email->id(), $contactLog->communication_type_id);
        $this->assertSame('outbound', $contactLog->direction);
        $this->assertStringContainsString('Emailed warranty claim', (string) $contactLog->notes);
        $this->assertStringContainsString('pat@acme.test', (string) $contactLog->notes);
        $this->assertContains('warranty_claim', $contactLog->tags ?? []);

        $vendorLog = Communication::query()
            ->where('communicable_type', Vendor::class)
            ->where('communicable_id', $vendor->id)
            ->first();

        $this->assertNotNull($vendorLog);
        $this->assertStringContainsString('manufacturer portal sign-in', (string) $vendorLog->notes);
    }
}
