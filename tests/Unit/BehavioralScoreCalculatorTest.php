<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;
use App\Domain\Score\Support\BehavioralScoreCalculator;
use App\Enums\Entity\PurchaseTimeline;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BehavioralScoreCalculatorTest extends TestCase
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

        $this->createTenantSchema();
    }

    public function test_lead_score_includes_profile_and_intent_signals(): void
    {
        $contact = Contact::query()->create([
            'first_name' => 'Alex',
            'last_name' => 'River',
            'email' => 'alex@example.com',
            'phone' => '555-0100',
            'company' => 'Harbor Co',
        ]);

        $lead = Lead::query()->create([
            'contact_id' => $contact->id,
            'interested_model' => 'AB 280',
            'has_trade_in' => true,
            'budget_max' => 120_000,
            'purchase_timeline' => PurchaseTimeline::Immediate->value,
            'marketing_opt_in' => true,
            'assigned_user_id' => 1,
        ]);

        $result = app(BehavioralScoreCalculator::class)->calculate($lead);

        $this->assertGreaterThan(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
        $this->assertNotEmpty($result['breakdown']);
        $this->assertTrue(collect($result['breakdown'])->contains('component', 'has_email'));
        $this->assertTrue(collect($result['breakdown'])->contains('component', 'boat_interest'));
        $this->assertTrue(collect($result['breakdown'])->contains('component', 'immediate_timeline'));
        $this->assertSame('lead_pipeline', $result['meta']['stage']);
    }

    public function test_lead_score_includes_linked_customer_profile(): void
    {
        $contact = Contact::query()->create([
            'first_name' => 'Sam',
            'last_name' => 'Boat',
            'email' => 'sam@example.com',
        ]);

        $lead = Lead::query()->create([
            'contact_id' => $contact->id,
        ]);

        Customer::query()->create([
            'contact_id' => $contact->id,
        ]);

        $result = app(BehavioralScoreCalculator::class)->calculate($lead->fresh());

        $this->assertTrue(collect($result['breakdown'])->contains('component', 'linked_customer'));
    }

    public function test_contact_score_includes_role_profiles(): void
    {
        $contact = Contact::query()->create([
            'first_name' => 'Pat',
            'last_name' => 'Mariner',
            'email' => 'pat@example.com',
        ]);

        Lead::query()->create(['contact_id' => $contact->id]);
        Customer::query()->create(['contact_id' => $contact->id]);

        $result = app(BehavioralScoreCalculator::class)->calculate($contact);

        $this->assertTrue(collect($result['breakdown'])->contains('component', 'has_lead'));
        $this->assertTrue(collect($result['breakdown'])->contains('component', 'has_customer'));
        $this->assertSame('contact_crm', $result['meta']['stage']);
    }

    private function createTenantSchema(): void
    {
        Schema::connection('tenant')->create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('company')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('contact_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->string('address_line_1')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('lead_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->string('interested_model')->nullable();
            $table->boolean('has_trade_in')->default(false);
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->string('purchase_timeline')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->boolean('is_qualified')->default(false);
            $table->date('last_contacted_at')->nullable();
            $table->date('next_followup_at')->nullable();
            $table->unsignedBigInteger('converted_customer_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('converted_from_lead_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('lead_profiles')->cascadeOnDelete();
            $table->integer('sequence')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->integer('sequence')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->integer('sequence')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('communications', function (Blueprint $table) {
            $table->id();
            $table->morphs('communicable');
            $table->string('channel')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('tasks', function (Blueprint $table) {
            $table->id();
            $table->morphs('relatable');
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('boat_show_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boat_show_id')->nullable();
            $table->unsignedBigInteger('boat_show_event_id')->nullable();
            $table->morphs('leadable');
            $table->timestamps();
        });

        Schema::connection('tenant')->create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->unsignedBigInteger('primary_contact_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('contact_vendor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
        });
    }
}
