<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Account;
use App\Support\TenantDashboardUrl;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class TenantDashboardUrlTest extends TestCase
{
    #[Test]
    public function it_builds_tenant_subdomain_dashboard_url_when_domain_exists(): void
    {
        config(['app.url' => 'https://app.example.com']);

        $account = new Account(['name' => 'Marina One']);
        $domain = new Domain(['domain' => '482910.example.com']);
        $account->setRelation('domains', new Collection([$domain]));

        $this->assertSame(
            'https://482910.example.com/',
            TenantDashboardUrl::forAccount($account)
        );
    }

    #[Test]
    public function it_falls_back_to_central_dashboard_when_no_domain(): void
    {
        $account = new Account(['name' => 'Pending']);
        $account->setRelation('domains', new Collection);

        $url = TenantDashboardUrl::forAccount($account);

        $this->assertStringContainsString('/dashboard', $url);
    }
}
