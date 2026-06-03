<?php

namespace Tests\Unit;

use App\Services\TenantStaffResolver;
use Tests\TestCase;

class TenantStaffResolverTest extends TestCase
{
    public function test_tenant_staff_for_web_user_returns_null_when_web_user_is_null(): void
    {
        $this->assertNull(TenantStaffResolver::tenantStaffForWebUser(null));
    }

    public function test_current_tenant_user_id_is_null_outside_tenant_context(): void
    {
        $this->assertNull(current_tenant_user_id());
    }
}
