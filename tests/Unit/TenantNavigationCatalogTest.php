<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TenantNavigation\TenantNavigationCatalog;
use Tests\TestCase;

class TenantNavigationCatalogTest extends TestCase
{
    public function test_permission_key_for_invoice_route(): void
    {
        $this->assertSame('invoice.view', TenantNavigationCatalog::permissionKeyForRoute('invoices.index'));
    }

    public function test_permission_key_for_dashboard_is_null(): void
    {
        $this->assertNull(TenantNavigationCatalog::permissionKeyForRoute('dashboard'));
    }

    public function test_flattened_catalog_includes_group_paths(): void
    {
        $entries = TenantNavigationCatalog::flattened();

        $this->assertNotEmpty($entries);

        $salesOverview = collect($entries)->firstWhere('route', 'sales.index');
        $this->assertNotNull($salesOverview);
        $this->assertSame(['Sales'], $salesOverview['group_path']);
    }
}
