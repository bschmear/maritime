<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the tenant's database.
     */

    // php artisan tenants:seed --force
    // php artisan tenants:seed --tenants=eb486884-8d2a-46cf-949c-f243c54c61d5 --force
    // php artisan tenants:seed --class=Database\\Seeders\\AccountSetupStepSeeder --force
    //
    // Shared tenant data seeders (boat options, etc.):
    // php artisan tenants:seed-data boat-options --tenant=eb486884-8d2a-46cf-949c-f243c54c61d5
    // php artisan tenants:seed-data boat-options --tenant=1
    // php artisan tenants:seed --tenants=eb486884-8d2a-46cf-949c-f243c54c61d5 --class="Database\\Seeders\\BoatOptionsSeeder"
    // php artisan tenants:seed-data --list

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AccountSettingsSeeder::class,
            AccountSetupStepSeeder::class,
            ConsignmentPolicySeeder::class,
            AssetSpecDefinitionSeeder::class,
            MaintenanceTypeSeeder::class,
            DeliveryChecklistCategorySeeder::class,
            AssetOptionCategorySeeder::class,
            ChartOfAccountSeeder::class,
        ]);
    }
}
