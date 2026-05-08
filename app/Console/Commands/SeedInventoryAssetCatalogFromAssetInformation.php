<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\InventoryCatalog\Services\AssetInformationInventorySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SeedInventoryAssetCatalogFromAssetInformation extends Command
{
    protected $signature = 'inventory:seed-asset-catalog
                            {--brand= : Only this BoatMake / directory slug (e.g. ab-inflatables)}
                            {--dry-run : Validate and report counts without writing to the inventory database}
                            {--keep-orphan-variants : Do not delete inventory variants whose keys are absent from meta.json}';

    protected $description = 'Seed inventory boat_make, assets, and asset_variants from app/AssetInformation/{brand}/meta.json (requires inventory migrations + lookup tables).';

    public function handle(AssetInformationInventorySeeder $seeder): int
    {
        $brand = $this->option('brand');
        $brandFilter = is_string($brand) && $brand !== '' ? $brand : null;
        $dryRun = (bool) $this->option('dry-run');
        $keepOrphans = (bool) $this->option('keep-orphan-variants');

        if (! $dryRun && (! Schema::connection('inventory')->hasColumn('assets', 'catalog_data')
            || ! Schema::connection('inventory')->hasColumn('assets', 'features')
            || ! Schema::connection('inventory')->hasColumn('assets', 'length_mm'))) {
            $this->error('Inventory `assets` is missing required columns (`catalog_data`, `features`, and meta specification columns such as `length_mm`). Run inventory migrations (database/migrations/inventory) against the inventory connection.');

            return self::FAILURE;
        }

        $rows = $seeder->run($brandFilter, $dryRun, $keepOrphans);

        foreach ($rows as $row) {
            $line = "[{$row['brand']}] {$row['status']}";
            if (isset($row['message'])) {
                $line .= ' — '.$row['message'];
            }
            if (isset($row['assets'], $row['variants'])) {
                $line .= " — assets: {$row['assets']}, variants: {$row['variants']}";
            }
            if (isset($row['make_lookups'])) {
                $line .= " — make_lookups: {$row['make_lookups']}";
            }

            match ($row['status']) {
                'error' => $this->error($line),
                'skipped' => $this->warn($line),
                default => $this->info($line),
            };
        }

        foreach ($rows as $r) {
            if ($r['status'] === 'error') {
                return self::FAILURE;
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry run: nothing was written to the inventory database. Run the same command without --dry-run to upsert boat_make, assets, and variants.');
        }

        return self::SUCCESS;
    }
}
