<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\Asset\Actions\UpdateAsset;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Support\SyncAssetSpecValues;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Enums\Inventory\BoatType;
use App\Enums\Inventory\HullMaterial;
use App\Enums\Inventory\HullType;
use App\Support\LengthMillimeters;
use Illuminate\Support\Facades\DB;

class AssetModelsGoogleSheetImportService
{
    public function __construct(
        private readonly AssetModelsGoogleSheetColumnRegistry $columns = new AssetModelsGoogleSheetColumnRegistry,
        private readonly AssetModelsGoogleSheetRowResolver $rowResolver = new AssetModelsGoogleSheetRowResolver,
        private readonly GoogleSheetSpecSupport $specs = new GoogleSheetSpecSupport,
        private readonly ?UpdateAsset $assetUpdater = null,
    ) {}

    /**
     * @param  list<list<mixed>>  $sheetRows
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
                [$asset, $variant, $resolveError] = $this->rowResolver->resolve($row, $i + 1);

                if ($resolveError !== null) {
                    $skipped++;
                    $errors[] = $resolveError;

                    continue;
                }

                if ($asset === null) {
                    $skipped++;

                    continue;
                }

                $assetChanges = $this->assetFieldChanges($asset, $row);
                $variantChanges = $variant !== null
                    ? $this->variantFieldChanges($variant, $row)
                    : [];
                $specPayload = $this->specPayloadFromRow($row, $specDefinitions, $asset, $variant);

                if ($assetChanges === [] && $variantChanges === [] && $specPayload === []) {
                    $noChanges++;

                    continue;
                }

                if ($assetChanges !== []) {
                    $result = $this->assetUpdater()($asset->id, $assetChanges);
                    if (! ($result['success'] ?? false)) {
                        $errors[] = 'Row '.($i + 1).': '.($result['message'] ?? 'Asset update failed.');

                        continue;
                    }
                    $asset->refresh();
                }

                if ($variantChanges !== [] && $variant !== null) {
                    $variant->update($variantChanges);
                    $variant->refresh();
                }

                if ($specPayload !== []) {
                    $specable = $this->specs->resolveSpecable($asset, $variant);
                    SyncAssetSpecValues::forSpecable($specable, (int) $asset->type, $specPayload);
                }

                $updated++;
                $previewRows[] = [
                    'row_index' => $i,
                    'asset_id' => $asset->id,
                    'variant_id' => $variant?->id,
                    'asset_changes' => $assetChanges,
                    'variant_changes' => $variantChanges,
                    'spec_count' => count($specPayload),
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
    private function assetFieldChanges(Asset $asset, array $row): array
    {
        $changes = [];

        $year = $row[AssetModelsGoogleSheetColumnRegistry::HEADER_MODEL_YEAR] ?? null;
        if (! $this->isBlank($year) && (string) $asset->year !== trim((string) $year)) {
            $changes['year'] = trim((string) $year);
        }

        foreach ([
            AssetModelsGoogleSheetColumnRegistry::HEADER_HULL_TYPE => ['field' => 'hull_type', 'enum' => HullType::class],
            AssetModelsGoogleSheetColumnRegistry::HEADER_HULL_MATERIAL => ['field' => 'hull_material', 'enum' => HullMaterial::class],
            AssetModelsGoogleSheetColumnRegistry::HEADER_BOAT_TYPE => ['field' => 'boat_type', 'enum' => BoatType::class],
        ] as $header => $config) {
            if (! array_key_exists($header, $row) || $this->isBlank($row[$header])) {
                continue;
            }
            $resolved = $this->resolveEnum($row[$header], $config['enum']);
            if ($resolved !== null && (int) $asset->{$config['field']} !== $resolved) {
                $changes[$config['field']] = $resolved;
            }
        }

        if (! $asset->has_variants) {
            $length = $this->parseLengthMm($row[AssetModelsGoogleSheetColumnRegistry::HEADER_LENGTH] ?? null);
            if ($length !== null && (int) $asset->length !== $length) {
                $changes['length'] = $length;
            }

            $width = $this->parseLengthMm($row[AssetModelsGoogleSheetColumnRegistry::HEADER_WIDTH] ?? null);
            if ($width !== null && (int) $asset->width !== $width) {
                $changes['width'] = $width;
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function variantFieldChanges(AssetVariant $variant, array $row): array
    {
        $changes = [];

        $length = $this->parseLengthMm($row[AssetModelsGoogleSheetColumnRegistry::HEADER_LENGTH] ?? null);
        if ($length !== null && (int) $variant->length !== $length) {
            $changes['length'] = $length;
        }

        $width = $this->parseLengthMm($row[AssetModelsGoogleSheetColumnRegistry::HEADER_WIDTH] ?? null);
        if ($width !== null && (int) $variant->width !== $width) {
            $changes['width'] = $width;
        }

        return $changes;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<\App\Domain\AssetSpec\Models\AssetSpecDefinition>  $specDefinitions
     * @return list<array<string, mixed>>
     */
    private function specPayloadFromRow(array $row, array $specDefinitions, Asset $asset, ?AssetVariant $variant): array
    {
        $payload = [];
        $specable = $this->specs->resolveSpecable($asset, $variant);
        $existing = $this->specs->loadSpecValues(
            $specDefinitions,
            array_map(fn ($d) => $d->id, $specDefinitions),
            [[$specable->getMorphClass(), (int) $specable->getKey()]],
        );
        $key = $this->specs->specableKey($specable);
        $currentValues = $existing[$key] ?? [];

        foreach ($specDefinitions as $definition) {
            $header = GoogleSheetSpecSupport::SPEC_PREFIX.$definition->label;
            if (! array_key_exists($header, $row)) {
                continue;
            }

            $raw = (string) ($row[$header] ?? '');
            if ($this->isBlank($raw)) {
                continue;
            }

            $entry = $this->specs->specPayload($definition, $raw);
            $current = $currentValues[$definition->id] ?? null;
            $formattedCurrent = $this->specs->formatSpecValue($current, $definition);
            $formattedNew = $this->specs->formatSpecValue(
                $this->specValueFromPayload($entry, $definition),
                $definition,
            );

            if ($formattedCurrent !== $formattedNew) {
                $payload[] = $entry;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function specValueFromPayload(array $entry, \App\Domain\AssetSpec\Models\AssetSpecDefinition $definition): \App\Domain\AssetSpec\Models\AssetSpecValue
    {
        $value = new \App\Domain\AssetSpec\Models\AssetSpecValue;
        $value->asset_spec_definition_id = $definition->id;
        $value->value_number = $entry['value_number'] ?? null;
        $value->value_text = $entry['value_text'] ?? null;
        $value->value_boolean = $entry['value_boolean'] ?? null;

        return $value;
    }

    private function parseLengthMm(?string $value): ?int
    {
        if ($this->isBlank($value)) {
            return null;
        }

        return LengthMillimeters::fromLegacyString($value);
    }

    /**
     * @param  class-string  $enumClass
     */
    private function resolveEnum(?string $value, string $enumClass): ?int
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

    private function assetUpdater(): UpdateAsset
    {
        return $this->assetUpdater ?? app(UpdateAsset::class);
    }
}
