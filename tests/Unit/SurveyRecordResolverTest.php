<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;
use App\Support\Survey\SurveyRecordResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SurveyRecordResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
        ]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('secondary_email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('contact_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('lead_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_customer_resolves_to_contact_id_for_signed_url(): void
    {
        $contact = Contact::query()->create([
            'display_name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'mobile' => '555-0100',
            'assigned_user_id' => 7,
        ]);

        $customer = Customer::query()->create([
            'contact_id' => $contact->id,
            'assigned_user_id' => 9,
        ]);

        $target = app(SurveyRecordResolver::class)->resolve('customer', (int) $customer->id);

        $this->assertNotNull($target);
        $this->assertSame('customer', $target->recordType);
        $this->assertSame((int) $customer->id, $target->recordId);
        $this->assertSame((int) $contact->id, $target->contactId);
        $this->assertSame('contact', $target->signedRecipientType);
        $this->assertSame((int) $contact->id, $target->signedRecipientId);
        $this->assertSame('jane@example.com', $target->email);
        $this->assertSame(9, $target->assignedUserId);
    }

    public function test_lead_uses_lead_id_for_signed_url(): void
    {
        $contact = Contact::query()->create([
            'email' => 'lead-contact@example.com',
        ]);

        $lead = Lead::query()->create([
            'contact_id' => $contact->id,
            'assigned_user_id' => 3,
        ]);

        $target = app(SurveyRecordResolver::class)->resolve('lead', (int) $lead->id);

        $this->assertNotNull($target);
        $this->assertSame('lead', $target->signedRecipientType);
        $this->assertSame((int) $lead->id, $target->signedRecipientId);
        $this->assertSame(3, $target->assignedUserId);
    }
}
