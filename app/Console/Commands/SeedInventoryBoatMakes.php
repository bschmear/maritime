<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Support\ManufacturerCatalog;
use Illuminate\Console\Command;

class SeedInventoryBoatMakes extends Command
{
    protected $signature = 'inventory:seed-makes {--force : Upsert existing slugs}';

    protected $description = 'Seed inventory database boat_make rows from Domain/BoatMake/Schema/manufacturers.json (slug → display_name)';

    public function handle(): int
    {
        $entries = ManufacturerCatalog::entries();
        if ($entries === []) {
            $this->error('No manufacturers found at '.ManufacturerCatalog::jsonPath());

            return self::FAILURE;
        }

        $count = 0;
        foreach ($entries as $row) {
            InventoryBoatMake::query()->updateOrCreate(
                ['slug' => $row['slug']],
                ['display_name' => $row['display_name'], 'active' => true]
            );
            $count++;
        }

        $this->info("Upserted {$count} inventory boat_make rows.");

        return self::SUCCESS;
    }
}
