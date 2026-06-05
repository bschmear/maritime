<?php

namespace Tests\Feature;

use App\Services\Sales\SalesOverviewDataService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Tests\TestCase;

class SalesOverviewBuildTest extends TestCase
{
    public function test_sales_overview_build_returns_summary_and_charts_when_tenant_db_available(): void
    {
        try {
            $data = app(SalesOverviewDataService::class)->build(Request::create('/sales', 'GET', [
                'period' => 'month',
            ]));
        } catch (QueryException $e) {
            $this->markTestSkipped('Tenant database not available in this environment: '.$e->getMessage());
        }

        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('charts', $data);
        $this->assertArrayHasKey('quickLinks', $data);
        $this->assertArrayHasKey('filters', $data);
        $this->assertArrayHasKey('salespeople', $data);
        $this->assertArrayHasKey('locations', $data);
        $this->assertSame('month', $data['filters']['period']);
        $this->assertCount(6, $data['summary']);
        $this->assertArrayHasKey('opportunities_by_status', $data['charts']);
        $this->assertArrayHasKey('activity_trend', $data['charts']);
    }
}
