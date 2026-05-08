<?php

namespace Database\Seeders;

use App\Domain\DeliveryChecklistCategory\Models\DeliveryChecklistCategory;
use Illuminate\Database\Seeder;

class DeliveryChecklistCategorySeeder extends Seeder
{
    /**
     * Default delivery checklist categories for each tenant.
     */
    // php artisan tenants:seed --class=DeliveryChecklistCategorySeeder --tenants=69d198f2-d0fc-4444-9692-c76e7491a633
    public function run(): void
    {
        DeliveryChecklistCategory::ensureDefaultsExist();
    }
}