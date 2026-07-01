<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Enums\ServiceTicket\Status as ServiceTicketStatus;
use App\Http\Controllers\Tenant\ServiceTicketController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceTicketIndexStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
        ]);
        DB::purge('tenant');

        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('service_ticket_number')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->boolean('approved')->default(false);
            $table->boolean('requires_reauthorization')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_index_stats_uses_single_aggregate_query(): void
    {
        ServiceTicket::withoutEvents(function () {
            ServiceTicket::query()->create([
                'service_ticket_number' => 'ST-1001',
                'status' => ServiceTicketStatus::Open->id(),
                'approved' => false,
                'requires_reauthorization' => false,
            ]);
            ServiceTicket::query()->create([
                'service_ticket_number' => 'ST-1002',
                'status' => ServiceTicketStatus::InProgress->id(),
                'approved' => true,
                'requires_reauthorization' => false,
            ]);
            ServiceTicket::query()->create([
                'service_ticket_number' => 'ST-1003',
                'status' => ServiceTicketStatus::Open->id(),
                'approved' => false,
                'requires_reauthorization' => true,
            ]);
        });

        DB::enableQueryLog();
        DB::flushQueryLog();

        $controller = new class extends ServiceTicketController
        {
            public function __construct() {}

            public function stats(): array
            {
                return $this->indexStats();
            }
        };

        $stats = $controller->stats();

        $ticketQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'service_tickets'))
            ->count();

        $this->assertSame(3, $stats['open']);
        $this->assertSame(1, $stats['approved']);
        $this->assertSame(1, $stats['in_progress']);
        $this->assertSame(1, $stats['needs_reauth']);
        $this->assertSame(1, $ticketQueries);
    }
}
