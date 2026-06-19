<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Lead\Models\Lead;
use App\Enums\Leads\Status as LeadStatus;
use App\Support\Tenant\LeadPipelineCountCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadPipelineCountCacheTest extends TestCase
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
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('contact_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('lead_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedTinyInteger('status_id')->nullable();
            $table->boolean('converted')->default(false);
            $table->timestamps();
        });
    }

    public function test_counts_only_active_pipeline_leads(): void
    {
        $this->seedLead(LeadStatus::Open->id());
        $this->seedLead(LeadStatus::Contacted->id());
        $this->seedLead(LeadStatus::Qualified->id());
        $this->seedLead(LeadStatus::Disqualified->id());
        $this->seedLead(LeadStatus::Open->id(), converted: true);

        LeadPipelineCountCache::forget();

        $this->assertSame(3, LeadPipelineCountCache::get());
    }

    private function seedLead(int $statusId, bool $converted = false): Lead
    {
        $contact = Contact::query()->create([
            'display_name' => 'Lead',
            'first_name' => 'Lead',
            'email' => uniqid('lead', true).'@example.com',
            'type' => '1',
            'status' => '1',
        ]);

        return Lead::query()->create([
            'contact_id' => $contact->id,
            'status_id' => $statusId,
            'converted' => $converted,
        ]);
    }
}
