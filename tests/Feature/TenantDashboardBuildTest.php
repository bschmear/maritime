<?php

namespace Tests\Feature;

use App\Services\Dashboard\TenantDashboardDataService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantDashboardBuildTest extends TestCase
{
    public function test_tenant_dashboard_build_returns_expected_top_level_keys_when_tenant_db_available(): void
    {
        try {
            $data = app(TenantDashboardDataService::class)->build(Request::create('/'));
        } catch (QueryException $e) {
            $this->markTestSkipped('Tenant database not available in this environment: '.$e->getMessage());
        }

        $this->assertSame(TenantDashboardDataService::SECTION_KEYS, array_keys($data));
    }
}
