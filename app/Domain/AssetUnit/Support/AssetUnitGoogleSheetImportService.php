<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\Asset\Actions\UpdateAssetUnit;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;
use Illuminate\Support\Facades\DB;

class AssetUnitGoogleSheetImportService
{
    public function __construct(
        private readonly AssetUnitGoogleSheetColumnRegistry $columns = new AssetUnitGoogleSheetColumnRegistry,
        private readonly AssetUnitGoogleSheetUnitResolver $unitResolver = new AssetUnitGoogleSheetUnitResolver,
        private readonly ?UpdateAssetUnit $unitUpdater = null,
    ) {}

    /**
     * @param  list<list<mixed>>  $sheetRows  First row must be headers
     * @return array{
     *   updated: int,
     *   skipped: int,
     *   no_changes: int,
     *   errors: list<string>,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function import(array $sheetRows): array
    {
        if ($sheetRows === []) {
            return [
                'updated' => 0,
                'skipped' => 0,
                'no_changes' => 0,
                'errors' => ['Sheet is empty.'],
                'rows' => [],
            ];
        }

        $headers = array_map(fn ($h) => trim((string) $h), $sheetRows[0]);
        $headerMap = [];
        foreach ($headers as $index => $header) {
            if ($header !== '') {
                $headerMap[$header] = $index;
            }
        }

        $updated = 0;
        $skipped = 0;
        $noChanges = 0;
        $errors = [];
        $previewRows = [];

        DB::transaction(function () use (
            $sheetRows,
            $headerMap,
            &$updated,
            &$skipped,
            &$noChanges,
            &$errors,
            &$previewRows,
        ): void {
            for ($i = 1; $i < count($sheetRows); $i++) {
                $cells = $sheetRows[$i];
                $row = $this->assocRow($headerMap, $cells);
                [$unit, $resolveError] = $this->unitResolver->resolve($row, $i + 1);

                if ($resolveError !== null) {
                    $skipped++;
                    $errors[] = $resolveError;

                    continue;
                }

                if ($unit === null) {
                    $skipped++;

                    continue;
                }

                $unit->loadMissing(['asset', 'location', 'subsidiary']);

                $unitChanges = $this->unitFieldChanges($unit, $row);

                if ($unitChanges === []) {
                    $noChanges++;

                    continue;
                }

                $payload = array_merge([
                    'condition' => $unit->condition,
                    'status' => $unit->status,
                ], $unitChanges);

                $result = $this->unitUpdater()($unit->id, $payload);
                if (! ($result['success'] ?? false)) {
                    $errors[] = 'Row '.($i + 1).': '.($result['message'] ?? 'Unit update failed.');

                    continue;
                }

                $updated++;
                $previewRows[] = [
                    'row_index' => $i,
                    'unit_id' => $unit->id,
                    'match_key' => filled($unit->hin) ? 'hin:'.$unit->hin : 'serial:'.$unit->serial_number,
                    'unit_changes' => $unitChanges,
                ];
            }
        });

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'no_changes' => $noChanges,
            'errors' => $errors,
            'rows' => $previewRows,
        ];
    }

    /**
     * @param  array<string, int>  $headerMap
     * @param  list<mixed>  $cells
     * @return array<string, string|null>
     */
    private function assocRow(array $headerMap, array $cells): array
    {
        $row = [];
        foreach ($headerMap as $header => $index) {
            $row[$header] = isset($cells[$index]) ? trim((string) $cells[$index]) : null;
        }

        return $row;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function unitFieldChanges(AssetUnit $unit, array $row): array
    {
        $changes = [];

        $status = $this->resolveStatus($row[AssetUnitGoogleSheetColumnRegistry::HEADER_STATUS] ?? null);
        if ($status !== null && (int) $unit->status !== $status) {
            $changes['status'] = $status;
        }

        $condition = $this->resolveCondition($row[AssetUnitGoogleSheetColumnRegistry::HEADER_CONDITION] ?? null);
        if ($condition !== null && (int) $unit->condition !== $condition) {
            $changes['condition'] = $condition;
        }

        $year = $row[AssetUnitGoogleSheetColumnRegistry::HEADER_UNIT_YEAR] ?? null;
        if (! $this->isBlank($year) && (string) $unit->year !== trim((string) $year)) {
            $changes['year'] = trim((string) $year);
        }

        foreach ([
            AssetUnitGoogleSheetColumnRegistry::HEADER_COST => 'cost',
            AssetUnitGoogleSheetColumnRegistry::HEADER_ASKING_PRICE => 'asking_price',
        ] as $header => $field) {
            if (! array_key_exists($header, $row) || $this->isBlank($row[$header])) {
                continue;
            }
            $parsed = AssetUnitSpreadsheetParser::parseCurrency($row[$header]);
            $current = $unit->{$field} !== null ? round((float) $unit->{$field}, 2) : null;
            $new = $parsed !== null ? round($parsed, 2) : null;
            if ($current !== $new) {
                $changes[$field] = $new;
            }
        }

        $locationName = $row[AssetUnitGoogleSheetColumnRegistry::HEADER_LOCATION] ?? null;
        if (! $this->isBlank($locationName)) {
            $locationId = Location::query()
                ->whereRaw('LOWER(display_name) = ?', [strtolower(trim((string) $locationName))])
                ->value('id');
            if ($locationId && (int) $unit->location_id !== (int) $locationId) {
                $changes['location_id'] = (int) $locationId;
            }
        }

        $subsidiaryName = $row[AssetUnitGoogleSheetColumnRegistry::HEADER_SUBSIDIARY] ?? null;
        if (! $this->isBlank($subsidiaryName)) {
            $subsidiaryId = Subsidiary::query()
                ->whereRaw('LOWER(display_name) = ?', [strtolower(trim((string) $subsidiaryName))])
                ->value('id');
            if ($subsidiaryId && (int) $unit->subsidiary_id !== (int) $subsidiaryId) {
                $changes['subsidiary_id'] = (int) $subsidiaryId;
            }
        }

        $variantLabel = $row[AssetUnitGoogleSheetColumnRegistry::HEADER_VARIANT] ?? null;
        if (! $this->isBlank($variantLabel) && $unit->asset_id) {
            $variantId = $this->resolveVariantId((int) $unit->asset_id, (string) $variantLabel);
            if ($variantId !== null && (int) $unit->asset_variant_id !== $variantId) {
                $changes['asset_variant_id'] = $variantId;
            }
        }

        return $changes;
    }

    private function resolveVariantId(int $assetId, string $label): ?int
    {
        $normalized = strtolower(trim($label));

        $variant = AssetVariant::query()
            ->where('asset_id', $assetId)
            ->where(function ($q) use ($normalized) {
                $q->whereRaw('LOWER(display_name) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first();

        return $variant?->id;
    }

    private function resolveStatus(?string $value): ?int
    {
        return $this->reflectionResolve($value, UnitStatus::class);
    }

    private function resolveCondition(?string $value): ?int
    {
        return $this->reflectionResolve($value, UnitCondition::class);
    }

    /**
     * @param  class-string  $enumClass
     */
    private function reflectionResolve(?string $value, string $enumClass): ?int
    {
        if ($this->isBlank($value) || ! method_exists($enumClass, 'options')) {
            return null;
        }

        $trimmed = trim((string) $value);
        foreach ($enumClass::options() as $option) {
            if (
                (string) ($option['id'] ?? '') === $trimmed
                || strcasecmp((string) ($option['name'] ?? ''), $trimmed) === 0
                || strcasecmp((string) ($option['value'] ?? ''), $trimmed) === 0
            ) {
                return (int) $option['id'];
            }
        }

        return null;
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    private function unitUpdater(): UpdateAssetUnit
    {
        return $this->unitUpdater ?? app(UpdateAssetUnit::class);
    }
}
