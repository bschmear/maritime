<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Support\ManufacturerCatalog;
use App\Support\ManufacturerDetailsCatalog;
use Illuminate\Console\Command;

class SeedInventoryBoatMakes extends Command
{
    protected $signature = 'inventory:seed-makes
                            {--force : Upsert existing slugs}
                            {--overwrite-description : Replace existing inventory brand descriptions from manufacturer_details.json}';

    protected $description = 'Seed inventory database boat_make rows from manufacturers.json and manufacturer_details.json';

    public function handle(): int
    {
        $entries = ManufacturerCatalog::entries();
        if ($entries === []) {
            $this->error('No manufacturers found at '.ManufacturerCatalog::jsonPath());

            return self::FAILURE;
        }

        $overwriteDescription = (bool) $this->option('overwrite-description');
        $count = 0;
        $categoriesSynced = 0;

        foreach ($entries as $row) {
            $existing = InventoryBoatMake::query()->where('slug', $row['slug'])->first();
            $payload = [
                'display_name' => $row['display_name'],
                'active' => true,
                ...ManufacturerDetailsCatalog::inventoryPayload(
                    $row['slug'],
                    $overwriteDescription,
                    $existing?->description
                ),
            ];

            /** @var InventoryBoatMake $make */
            $make = InventoryBoatMake::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $payload
            );
            $categoriesSynced += ManufacturerDetailsCatalog::syncBoatTypesForMake($make);
            $count++;
        }

        $detailsSynced = $this->syncDetailsForExistingInventoryMakes($overwriteDescription);

        $this->info("Upserted {$count} inventory boat_make rows.");
        if ($detailsSynced > 0) {
            $this->info("Synced manufacturer details onto {$detailsSynced} existing inventory brand(s).");
        }
        if ($categoriesSynced > 0) {
            $this->info("Synced boat categories for {$categoriesSynced} brand/category link(s).");
        }

        return self::SUCCESS;
    }

    private function syncDetailsForExistingInventoryMakes(bool $overwriteDescription): int
    {
        $synced = 0;

        foreach (ManufacturerDetailsCatalog::allBySlug() as $slug => $details) {
            $make = InventoryBoatMake::query()->where('slug', $slug)->first();
            if ($make === null) {
                continue;
            }

            $payload = ManufacturerDetailsCatalog::inventoryPayload(
                $slug,
                $overwriteDescription,
                $make->description
            );
            if ($payload !== []) {
                $make->update($payload);
                $synced++;
            }

            ManufacturerDetailsCatalog::syncBoatTypesForMake($make);
        }

        return $synced;
    }
}
