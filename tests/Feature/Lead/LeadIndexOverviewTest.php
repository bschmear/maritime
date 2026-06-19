<?php

declare(strict_types=1);

namespace Tests\Feature\Lead;

use App\Domain\Contact\Models\Contact;
use App\Domain\Lead\Models\Lead;
use App\Enums\Leads\Status as LeadStatus;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Services\Leads\LeadOverviewDataService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadIndexOverviewTest extends TestCase
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
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('company')->nullable();
            $table->string('status')->nullable();
            $table->unsignedTinyInteger('stage_id')->nullable();
            $table->string('type')->nullable();
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
            $table->unsignedTinyInteger('source_id')->nullable();
            $table->unsignedTinyInteger('priority_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->boolean('converted')->default(false);
            $table->date('next_followup_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_lead_table_schema_default_filter_is_open_status_id(): void
    {
        $controller = new class extends Controller
        {
            use HasSchemaSupport;

            protected string $domainName = 'Lead';
        };

        $path = app_path('Domain/Lead/Schema/table.json');
        $schema = json_decode((string) file_get_contents($path), true);

        $method = new \ReflectionMethod($controller, 'defaultFiltersFromTableSchema');
        $method->setAccessible(true);
        $defaults = $method->invoke($controller, $schema);

        $this->assertCount(1, $defaults);
        $this->assertSame('status_id', $defaults[0]['field']);
        $this->assertSame('any_of', $defaults[0]['operator']);
        $this->assertSame([1], $defaults[0]['value']);
    }

    public function test_overview_service_builds_pipeline_stats(): void
    {
        $this->seedLead(LeadStatus::Open->id(), sourceId: 1);
        $this->seedLead(LeadStatus::Contacted->id(), sourceId: 2);
        $this->seedLead(LeadStatus::Qualified->id(), sourceId: 2, followUp: now()->subDay());
        $this->seedLead(LeadStatus::Converted->id(), converted: true);

        $stats = app(LeadOverviewDataService::class)->buildStats();

        $this->assertSame(1, $stats['open']);
        $this->assertSame(1, $stats['contacted']);
        $this->assertSame(1, $stats['qualified']);
        $this->assertSame(1, $stats['follow_up_due']);
    }

    public function test_overview_service_builds_chart_payloads(): void
    {
        $this->seedLead(LeadStatus::Open->id(), sourceId: 1);
        $this->seedLead(LeadStatus::Contacted->id(), sourceId: 2);

        $charts = app(LeadOverviewDataService::class)->buildCharts();

        $this->assertArrayHasKey('by_status', $charts);
        $this->assertArrayHasKey('by_source', $charts);
        $this->assertArrayHasKey('created_trend', $charts);
        $this->assertCount(2, $charts['by_status']['labels']);
        $this->assertCount(12, $charts['created_trend']['categories']);
        $this->assertCount(1, $charts['created_trend']['series']);
        $this->assertNotEmpty($charts['by_source']['colors']);
        $this->assertContains('#3b82f6', $charts['by_source']['colors']);
        $this->assertContains('#a855f7', $charts['by_source']['colors']);
    }

    public function test_open_leads_preview_returns_active_pipeline_leads(): void
    {
        $open = $this->seedLead(LeadStatus::Open->id(), name: 'Open Lead');
        $contacted = $this->seedLead(LeadStatus::Contacted->id(), name: 'Contacted Lead');
        $this->seedLead(LeadStatus::Qualified->id(), name: 'Qualified Lead');
        $this->seedLead(LeadStatus::Disqualified->id(), name: 'Disqualified Lead');
        $this->seedLead(LeadStatus::Open->id(), converted: true, name: 'Converted Lead');

        $preview = app(LeadOverviewDataService::class)->openLeadsPreview();

        $this->assertCount(3, $preview);
        $ids = array_column($preview, 'id');
        $this->assertContains($open->id, $ids);
        $this->assertContains($contacted->id, $ids);
    }

    private function seedLead(
        int $statusId,
        bool $converted = false,
        ?int $sourceId = null,
        ?Carbon $followUp = null,
        string $name = 'Test Lead',
    ): Lead {
        $contact = Contact::query()->create([
            'display_name' => $name,
            'first_name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'type' => '1',
            'status' => '1',
        ]);

        return Lead::query()->create([
            'contact_id' => $contact->id,
            'status_id' => $statusId,
            'source_id' => $sourceId,
            'converted' => $converted,
            'next_followup_at' => $followUp?->toDateString(),
            'created_at' => now(),
        ]);
    }
}
