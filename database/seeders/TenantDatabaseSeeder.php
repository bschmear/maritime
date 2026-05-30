<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the tenant's database.
     */

    // php artisan tenants:seed
    //  php artisan tenants:seed --tenants=eb486884-8d2a-46cf-949c-f243c54c61d5

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AccountSettingsSeeder::class,
            AssetSpecDefinitionSeeder::class,
            MaintenanceTypeSeeder::class,
            DeliveryChecklistCategorySeeder::class,
        ]);
    }
}
