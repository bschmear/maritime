<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\InventoryCatalog\Models\InventoryBoatMake;
use App\Support\InventoryCatalogImageStorage;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class SeedInventoryBrandLogos extends Command
{
    protected $signature = 'inventory:seed-brand-logos {--force : Re-upload even when logo_url is already set}';

    protected $description = 'Upload static brand logos from public/brand/images/brands/ to S3 and set inventory boat_make.logo_url';

    public function handle(): int
    {
        $directory = public_path('brand/images/brands');
        if (! File::isDirectory($directory)) {
            $this->error("Directory not found: {$directory}");

            return self::FAILURE;
        }

        $files = File::files($directory);
        if ($files === []) {
            $this->warn('No brand logo files found.');

            return self::SUCCESS;
        }

        $uploaded = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($files as $file) {
            $extension = strtolower($file->getExtension());
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }

            $slug = $file->getFilenameWithoutExtension();
            $make = InventoryBoatMake::query()->where('slug', $slug)->first();

            if ($make === null) {
                $this->warn("No inventory boat_make row for slug: {$slug}");
                $missing++;

                continue;
            }

            if ($make->logo_url && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            $uploadedFile = new UploadedFile(
                $file->getPathname(),
                $file->getFilename(),
                mime_content_type($file->getPathname()) ?: 'image/jpeg',
                null,
                true,
            );

            $result = InventoryCatalogImageStorage::store($uploadedFile, $make->logo_url);
            $make->update(['logo_url' => $result['url']]);

            $this->line("Uploaded logo for {$slug}");
            $uploaded++;
        }

        $this->info("Done. Uploaded: {$uploaded}, skipped: {$skipped}, missing make: {$missing}.");

        return self::SUCCESS;
    }
}
