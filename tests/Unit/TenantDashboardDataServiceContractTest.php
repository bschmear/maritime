<?php

namespace Tests\Unit;

use App\Services\Dashboard\TenantDashboardDataService;
use Tests\TestCase;

class TenantDashboardDataServiceContractTest extends TestCase
{
    public function test_section_keys_match_documented_dashboard_contract(): void
    {
        $this->assertSame(
            ['actionCenter', 'risk', 'revenue', 'operations', 'activity', 'meta'],
            TenantDashboardDataService::SECTION_KEYS
        );
    }
}
