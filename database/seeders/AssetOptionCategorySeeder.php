<?php

namespace Database\Seeders;

use App\Domain\AssetOptionCategory\Models\AssetOptionCategory;
use Illuminate\Database\Seeder;

class AssetOptionCategorySeeder extends Seeder
{
    /**
     * Default asset option categories for each new tenant.
     */
    public function run(): void
    {
        AssetOptionCategory::ensureDefaultsExist();
    }
}
