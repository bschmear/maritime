<?php

namespace Database\Seeders;

use App\Domain\DeliveryChecklistCategory\Models\DeliveryChecklistCategory;
use Illuminate\Database\Seeder;

class DeliveryChecklistCategorySeeder extends Seeder
{
    /**
     * Default delivery checklist categories for each tenant.
     */
    public function run(): void
    {
        DeliveryChecklistCategory::ensureDefaultsExist();
    }
}
