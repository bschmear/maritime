<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\BoatMake\Actions\ImportDiscoveredBoatModels;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\BoatMake\Models\BoatMakeModelImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class ProcessBoatMakeModelImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $boatMakeModelImportId,
    ) {}

    public function handle(ImportDiscoveredBoatModels $import): void
    {
        $row = BoatMakeModelImport::query()->find($this->boatMakeModelImportId);
        if ($row === null) {
            return;
        }

        if (in_array($row->status, [BoatMakeModelImport::STATUS_COMPLETED, BoatMakeModelImport::STATUS_SKIPPED], true)) {
            return;
        }

        $make = BoatMake::query()->find($row->boat_make_id);
        if ($make === null || $make->brand_key === null || $make->brand_key === '') {
            $row->update([
                'status' => BoatMakeModelImport::STATUS_FAILED,
                'error_message' => 'Brand is missing or invalid.',
            ]);

            return;
        }

        $slug = Str::slug($row->model_slug);
        $label = trim($row->model_label);
        if ($slug === '' || $label === '') {
            $row->update([
                'status' => BoatMakeModelImport::STATUS_FAILED,
                'error_message' => 'Invalid model slug or label.',
            ]);

            return;
        }

        $catalogKey = $make->brand_key.'--'.$slug;
        $row->update([
            'status' => BoatMakeModelImport::STATUS_PROCESSING,
            'catalog_asset_key' => $catalogKey,
            'error_message' => null,
        ]);

        try {
            $outcome = $import->importOne($make, $slug, $label);
        } catch (Throwable $e) {
            report($e);
            $row->update([
                'status' => BoatMakeModelImport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return;
        }

        match ($outcome) {
            'imported' => $row->update(['status' => BoatMakeModelImport::STATUS_COMPLETED]),
            'skipped_already_list' => $row->update(['status' => BoatMakeModelImport::STATUS_SKIPPED]),
            'failed' => $row->update([
                'status' => BoatMakeModelImport::STATUS_FAILED,
                'error_message' => 'Could not build or import this model. Check logs.',
            ]),
        };
    }
}
