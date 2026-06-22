<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetUnit\Actions\CreateAssetUnit;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\BoatMake\Models\BoatMake;
use App\Enums\Inventory\UnitStatus;
use Illuminate\Support\Facades\DB;

class InvoiceUnitImportService
{
    public function __construct(
        private readonly ?CreateAssetUnit $creator = null,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *   created: int,
     *   skipped: int,
     *   errors: list<string>,
     *   unit_ids: list<int>
     * }
     */
    public function import(BoatMake $brand, array $rows): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];
        $unitIds = [];

        foreach ($rows as $index => $row) {
            if (! ($row['include'] ?? true)) {
                $skipped++;

                continue;
            }

            $validationError = $this->validateRow($row, $index);
            if ($validationError !== null) {
                $errors[] = $validationError;
                $skipped++;

                continue;
            }

            $result = DB::transaction(function () use ($brand, $row, $index) {
                $assetId = (int) $row['asset_id'];
                $asset = Asset::query()->find($assetId);
                if ($asset === null) {
                    return [
                        'status' => 'skipped',
                        'message' => 'Row '.($index + 1).': asset not found.',
                    ];
                }

                $hin = isset($row['hin']) ? trim((string) $row['hin']) : '';
                if ($hin !== '' && AssetUnit::query()->where('hin', $hin)->exists()) {
                    return [
                        'status' => 'skipped',
                        'message' => 'Row '.($index + 1).": HIN {$hin} already exists.",
                    ];
                }

                $payload = [
                    'asset_id' => $assetId,
                    'asset_variant_id' => $asset->has_variants ? (int) $row['asset_variant_id'] : null,
                    'hin' => $hin !== '' ? $hin : null,
                    'serial_number' => isset($row['serial_number']) && trim((string) $row['serial_number']) !== ''
                        ? trim((string) $row['serial_number'])
                        : null,
                    'cost' => (float) ($row['unit_price'] ?? 0),
                    'condition' => (int) ($row['condition'] ?? 1),
                    'status' => (int) ($row['status'] ?? UnitStatus::Inbound->id()),
                    'vendor_id' => $brand->vendor_id,
                    'subsidiary_id' => ! empty($row['subsidiary_id']) ? (int) $row['subsidiary_id'] : null,
                    'location_id' => ! empty($row['location_id']) ? (int) $row['location_id'] : null,
                ];

                $createResult = $this->creator()($payload);
                if (! ($createResult['success'] ?? false)) {
                    return [
                        'status' => 'skipped',
                        'message' => 'Row '.($index + 1).': '.($createResult['message'] ?? 'Create failed.'),
                    ];
                }

                return [
                    'status' => 'created',
                    'unit_id' => isset($createResult['record']->id) ? (int) $createResult['record']->id : null,
                ];
            });

            if (($result['status'] ?? '') === 'created') {
                $created++;
                if (! empty($result['unit_id'])) {
                    $unitIds[] = (int) $result['unit_id'];
                }

                continue;
            }

            $skipped++;
            if (! empty($result['message'])) {
                $errors[] = (string) $result['message'];
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'unit_ids' => $unitIds,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function validateRow(array $row, int $index): ?string
    {
        $assetId = $row['asset_id'] ?? null;
        if (! $assetId) {
            return 'Row '.($index + 1).': select an asset before importing.';
        }

        $asset = Asset::query()->find((int) $assetId);
        if ($asset === null) {
            return 'Row '.($index + 1).': asset not found.';
        }

        if ($asset->has_variants && empty($row['asset_variant_id'])) {
            return 'Row '.($index + 1).': select a variant for this asset.';
        }

        return null;
    }

    private function creator(): CreateAssetUnit
    {
        return $this->creator ?? app(CreateAssetUnit::class);
    }
}
