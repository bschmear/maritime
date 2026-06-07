<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ConsignmentPolicy\Models\ConsignmentPolicy;
use App\Models\AccountSettings;
use Tests\TestCase;

class ConsignmentPolicyDefaultsTest extends TestCase
{
    public function test_default_bodies_are_defined(): void
    {
        $bodies = ConsignmentPolicy::defaultBodies();

        $this->assertNotEmpty($bodies);
        $this->assertStringContainsString(
            'space is available',
            $bodies[0],
        );
    }

    public function test_consignment_policy_seeder_is_registered_in_tenant_database_seeder(): void
    {
        $contents = (string) file_get_contents(base_path('database/seeders/TenantDatabaseSeeder.php'));

        $this->assertStringContainsString('ConsignmentPolicySeeder::class', $contents);
    }

    public function test_default_consignment_terms_are_defined(): void
    {
        $terms = AccountSettings::defaultConsignmentTerms();

        $this->assertNotEmpty(trim($terms));
        $this->assertStringContainsString('consignment agreement', strtolower($terms));
    }
}
