<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\Asset\Actions\UpdateAsset;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Support\SyncAssetSpecValues;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetUnit\Actions\UpdateAssetUnit;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\BoatMake\Models\BoatMake;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;
use Illuminate\Support\Facades\DB;

class AssetUnitGoogleSheetImportService
{
    public function __construct(
        private readonly AssetUnitGoogleSheetColumnRegistry $columns = new AssetUnitGoogleSheetColumnRegistry,
        private readonly AssetUnitImportService $unitImporter = new AssetUnitImportService,
        private readonly ?UpdateAssetUnit $unitUpdater = null,
        private readonly ?UpdateAsset $assetUpdater = null,
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

        $specDefinitions = $this->columns->specDefinitions();
        $updated = 0;
        $skipped = 0;
        $noChanges = 0;
        $errors = [];
        $previewRows = [];

        DB::transaction(function () use (
            $sheetRows,
            $headerMap,
            $specDefinitions,
            &$updated,
            &$skipped,
            &$noChanges,
            &$errors,
            &$previewRows,
        ): void {
            for ($i = 1; $i < count($sheetRows); $i++) {
                $cells = $sheetRows[$i];
                $row = $this->assocRow($headerMap, $cells);
                $unitId = (int) preg_replace('/\D/', '', (string) ($row[AssetUnitGoogleSheetColumnRegistry::HEADER_UNIT_ID] ?? ''));

                if ($unitId <= 0) {
                    $skipped++;

                    continue;
                }

                $unit = AssetUnit::query()
                    ->with(['asset.make', 'assetVariant', 'location'])
                    ->find($unitId);

                if ($unit === null) {
                    $skipped++;
                    $errors[] = 'Row '.($i + 1).": Unit #{$unitId} not found.";

                    continue;
                }

                $unitChanges = $this->unitFieldChanges($unit, $row);
                $parentChanges = $this->parentFieldChanges($unit, $row);
                $specChanges = $this->specChanges($unit, $row, $specDefinitions);

                $hasParentChanges = $parentChanges['asset'] !== []
                    || $parentChanges['variant'] !== []
                    || $parentChanges['unit'] !== [];

                if ($unitChanges === [] && ! $hasParentChanges && $specChanges === []) {
                    $noChanges++;

                    continue;
                }

                if ($unitChanges !== [] || $parentChanges['unit'] !== []) {
                    $payload = array_merge([
                        'condition' => $unit->condition,
                        'status' => $unit->status,
                    ], $unitChanges, $parentChanges['unit']);

                    $result = $this->unitUpdater()($unit->id, $payload);
                    if (! ($result['success'] ?? false)) {
                        $errors[] = 'Row '.($i + 1).': '.($result['message'] ?? 'Unit update failed.');

                        continue;
                    }
                }

                if ($hasParentChanges || $specChanges !== []) {
                    $this->applyParentAndSpecChanges($unit, $parentChanges, $specChanges, $specDefinitions);
                }

                $updated++;
                $previewRows[] = [
                    'row_index' => $i,
                    'unit_id' => $unitId,
                    'unit_changes' => $unitChanges,
                    'parent_changes' => $parentChanges,
                    'spec_changes' => count($specChanges),
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

        return $changes;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array{asset: array<string, mixed>, variant: array<string, mixed>, unit: array<string, mixed>}
     */
    private function parentFieldChanges(AssetUnit $unit, array $row): array
    {
        $assetChanges = [];
        $variantChanges = [];
        $unitChanges = [];

        $asset = $unit->asset;
        $variant = $unit->assetVariant;

        if ($asset) {
            $makeName = $row[AssetUnitGoogleSheetColumnRegistry::HEADER_MAKE] ?? null;
            if (! $this->isBlank($makeName)) {
                $makeId = BoatMake::query()
                    ->whereRaw('LOWER(display_name) = ?', [strtolower(trim((string) $makeName))])
                    ->value('id');
                if ($makeId && (int) $asset->make_id !== (int) $makeId) {
                    $assetChanges['make_id'] = (int) $makeId;
                }
            }

            $model = $row[AssetUnitGoogleSheetColumnRegistry::HEADER_ASSET_MODEL] ?? null;
            if (! $this->isBlank($model) && (string) $asset->model !== trim((string) $model)) {
                $assetChanges['model'] = trim((string) $model);
            }

            $year = $row[AssetUnitGoogleSheetColumnRegistry::HEADER_ASSET_YEAR] ?? null;
            if (! $this->isBlank($year) && (string) $asset->year !== trim((string) $year)) {
                $assetChanges['year'] = trim((string) $year);
            }

            if (! $variant) {
                $hullType = $this->resolveEnumId($row[AssetUnitGoogleSheetColumnRegistry::HEADER_HULL_TYPE] ?? null, HullType::class);
                if ($hullType !== null && (int) $asset->hull_type !== $hullType) {
                    $assetChanges['hull_type'] = $hullType;
                }

                $hullMaterial = $this->resolveEnumId($row[AssetUnitGoogleSheetColumnRegistry::HEADER_HULL_MATERIAL] ?? null, HullMaterial::class);
                if ($hullMaterial !== null && (int) $asset->hull_material !== $hullMaterial) {
                    $assetChanges['hull_material'] = $hullMaterial;
                }

                $boatType = $this->resolveEnumId($row[AssetUnitGoogleSheetColumnRegistry::HEADER_BOAT_TYPE] ?? null, BoatType::class);
                if ($boatType !== null && (int) $asset->boat_type !== $boatType) {
                    $assetChanges['boat_type'] = $boatType;
                }

                $maxHp = $row[AssetUnitGoogleSheetColumnRegistry::HEADER_MAX_HP] ?? null;
                if (! $this->isBlank($maxHp) && (string) $asset->maximum_power !== trim((string) $maxHp)) {
                    $assetChanges['maximum_power'] = trim((string) $maxHp);
                }
            }
        }

        $length = $this->parseLengthFeet($row[AssetUnitGoogleSheetColumnRegistry::HEADER_LENGTH] ?? null);
        $width = $this->parseLengthFeet($row[AssetUnitGoogleSheetColumnRegistry::HEADER_WIDTH] ?? null);

        if ($variant) {
            if ($length !== null && (int) $variant->length !== $length) {
                $variantChanges['length'] = $length;
            }
            if ($width !== null && (int) $variant->width !== $width) {
                $variantChanges['width'] = $width;
            }
        } elseif ($asset) {
            if ($length !== null && (int) $asset->length !== $length) {
                $assetChanges['length'] = $length;
            }
            if ($width !== null && (int) $asset->width !== $width) {
                $assetChanges['width'] = $width;
            }
        }

        $variantLabel = $row[AssetUnitGoogleSheetColumnRegistry::HEADER_VARIANT] ?? null;
        if (! $this->isBlank($variantLabel) && $unit->asset_id) {
            $variantId = $this->resolveVariantId((int) $unit->asset_id, (string) $variantLabel);
            if ($variantId !== null && (int) $unit->asset_variant_id !== $variantId) {
                $unitChanges['asset_variant_id'] = $variantId;
            }
        }

        return [
            'asset' => $assetChanges,
            'variant' => $variantChanges,
            'unit' => $unitChanges,
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<AssetSpecDefinition>  $definitions
     * @return list<array<string, mixed>>
     */
    private function specChanges(AssetUnit $unit, array $row, array $definitions): array
    {
        $changes = [];

        foreach ($definitions as $definition) {
            $header = AssetUnitGoogleSheetColumnRegistry::SPEC_PREFIX.$definition->label;
            if (! array_key_exists($header, $row) || $this->isBlank($row[$header])) {
                continue;
            }

            $changes[] = [
                'spec_id' => $definition->id,
                'value' => $row[$header],
                'type' => $definition->type,
            ];
        }

        return $changes;
    }

    /**
     * @param  array{asset: array<string, mixed>, variant: array<string, mixed>, unit: array<string, mixed>}  $parentChanges
     * @param  list<array<string, mixed>>  $specChanges
     * @param  list<AssetSpecDefinition>  $definitions
     */
    private function applyParentAndSpecChanges(
        AssetUnit $unit,
        array $parentChanges,
        array $specChanges,
        array $definitions,
    ): void {
        $unit->refresh(['asset.make', 'assetVariant']);

        if ($parentChanges['asset'] !== [] && $unit->asset) {
            $this->assetUpdater()($unit->asset_id, $parentChanges['asset']);
        }

        if ($parentChanges['variant'] !== [] && $unit->assetVariant) {
            $unit->assetVariant->update($parentChanges['variant']);
        }

        $specable = $this->specable($unit->fresh(['asset', 'assetVariant']));
        if ($specable === null) {
            return;
        }

        if ($specChanges !== []) {
            $definitionById = collect($definitions)->keyBy('id');
            $payload = [];
            foreach ($specChanges as $change) {
                $def = $definitionById->get($change['spec_id']);
                if (! $def) {
                    continue;
                }
                $payload[] = $this->specPayload($def, (string) $change['value']);
            }

            $assetType = (int) ($unit->asset?->type ?? 1);
            SyncAssetSpecValues::forSpecable($specable, $assetType, $payload);
        }
    }

    /**
     * @return array{spec_id: int, value_number?: mixed, value_text?: mixed, value_boolean?: bool}
     */
    private function specPayload(AssetSpecDefinition $definition, string $raw): array
    {
        $entry = ['spec_id' => $definition->id];

        if ($definition->type === 'boolean') {
            $entry['value_boolean'] = in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'y'], true);
        } elseif ($definition->type === 'number') {
            $clean = str_replace([',', '$'], '', trim($raw));
            $entry['value_number'] = is_numeric($clean) ? (float) $clean : null;
        } else {
            $entry['value_text'] = $raw;
        }

        return $entry;
    }

    private function specable(AssetUnit $unit): Asset|AssetVariant|null
    {
        if ($unit->asset_variant_id && $unit->assetVariant) {
            return $unit->assetVariant;
        }

        return $unit->asset;
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
    private function resolveEnumId(?string $value, string $enumClass): ?int
    {
        return $this->reflectionResolve($value, $enumClass);
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

    private function parseLengthFeet(?string $value): ?int
    {
        if ($this->isBlank($value)) {
            return null;
        }

        $clean = preg_replace('/[^0-9.]/', '', (string) $value);
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (int) round((float) $clean * 304.8);
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    private function unitUpdater(): UpdateAssetUnit
    {
        return $this->unitUpdater ?? app(UpdateAssetUnit::class);
    }

    private function assetUpdater(): UpdateAsset
    {
        return $this->assetUpdater ?? app(UpdateAsset::class);
    }
}
