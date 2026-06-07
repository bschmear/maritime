<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\ConsignmentPolicy\Models\ConsignmentPolicy;
use App\Models\AccountSettings;
use Illuminate\Database\Seeder;

class ConsignmentPolicySeeder extends Seeder
{
    /**
     * Seed default consignment policy bullets for the current tenant.
     *
     * Manual prod run (required --force):
     *   php artisan tenants:seed --class=Database\\Seeders\\ConsignmentPolicySeeder --force
     */
    public function run(): void
    {
        ConsignmentPolicy::ensureDefaultsExist();
        AccountSettings::ensureConsignmentDefaults();
    }
}
