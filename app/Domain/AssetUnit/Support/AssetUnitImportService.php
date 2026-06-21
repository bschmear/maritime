<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\AssetUnit\Actions\UpdateAssetUnit;
use App\Domain\AssetUnit\Models\AssetUnit;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AssetUnitImportService
{
    /** @var list<string> */
    private const UPDATABLE_FIELDS = ['status', 'condition', 'cost', 'asking_price'];

    public function __construct(
        private readonly AssetUnitSpreadsheetParser $parser = new AssetUnitSpreadsheetParser,
        private readonly ?UpdateAssetUnit $updater = null,
    ) {}

    /**
     * @param  list<array<string, string|null>>  $rows
     * @param  array<string, string>  $columnMap
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   summary: array{matched: int, unmatched: int, ambiguous: int, skipped: int, no_changes: int}
     * }
     */
    public function preview(
        array $rows,
        string $matchColumn,
        string $matchField,
        array $columnMap = [],
    ): array {
        $columnMap = $columnMap !== [] ? $columnMap : AssetUnitSpreadsheetParser::defaultColumnMap();
        $seenKeys = [];
        $previewRows = [];
        $matched = 0;
        $ambiguous = 0;
        $skipped = 0;
        $noChanges = 0;

        foreach ($rows as $index => $row) {
            $matchValue = $row[$matchColumn] ?? null;
            $units = $this->findUnits($matchField, $matchValue);
            $mapped = $this->mapRowToPayload($row, $columnMap);

            $status = 'unmatched';
            $assetUnit = null;

            if ($this->isBlank($matchValue)) {
                $status = 'unmatched';
            } elseif (count($units) > 1) {
                $status = 'ambiguous';
                $ambiguous++;
            } elseif (count($units) === 1) {
                $status = 'matched';
                $assetUnit = $units[0];
                $matched++;
            }

            $normalizedKey = $this->normalizeMatchKey($matchField, $matchValue);
            if ($normalizedKey !== '' && isset($seenKeys[$normalizedKey])) {
                $status = 'duplicate';
            }
            if ($normalizedKey !== '') {
                $seenKeys[$normalizedKey] = true;
            }

            $changes = $assetUnit ? $this->diffChanges($assetUnit, $mapped) : [];
            $action = 'skip';

            if ($status === 'duplicate') {
                $action = 'skip';
                $skipped++;
            } elseif ($assetUnit === null) {
                $action = 'skip';
                $skipped++;
            } elseif ($changes === []) {
                $action = 'no_change';
                $noChanges++;
            } else {
                $action = 'update';
            }

            $previewRows[] = [
                'row_index' => $index,
                'match_value' => $matchValue,
                'status' => $status,
                'action' => $action,
                'asset_unit' => $assetUnit ? [
                    'id' => $assetUnit->id,
                    'display_name' => $assetUnit->display_name,
                    'hin' => $assetUnit->hin,
                    'serial_number' => $assetUnit->serial_number,
                ] : null,
                'changes' => $changes,
                'mapped' => $mapped,
            ];
        }

        return [
            'rows' => $previewRows,
            'summary' => [
                'matched' => $matched,
                'unmatched' => count(array_filter(
                    $previewRows,
                    fn (array $row): bool => $row['asset_unit'] === null && $row['action'] === 'skip',
                )),
                'ambiguous' => $ambiguous,
                'skipped' => $skipped,
                'no_changes' => $noChanges,
            ],
        ];
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @param  array<string, string>  $columnMap
     * @return array{
     *   updated: int,
     *   skipped: int,
     *   no_changes: int,
     *   errors: list<string>
     * }
     */
    public function import(
        array $rows,
        string $matchColumn,
        string $matchField,
        array $columnMap = [],
    ): array {
        $columnMap = $columnMap !== [] ? $columnMap : AssetUnitSpreadsheetParser::defaultColumnMap();

        $updated = 0;
        $skipped = 0;
        $noChanges = 0;
        $errors = [];

        DB::transaction(function () use (
            $rows,
            $matchColumn,
            $matchField,
            $columnMap,
            &$updated,
            &$skipped,
            &$noChanges,
            &$errors,
        ): void {
            $seenKeys = [];

            foreach ($rows as $index => $row) {
                $matchValue = $row[$matchColumn] ?? null;
                $normalizedKey = $this->normalizeMatchKey($matchField, $matchValue);

                if ($normalizedKey === '' || isset($seenKeys[$normalizedKey])) {
                    $skipped++;

                    continue;
                }
                $seenKeys[$normalizedKey] = true;

                $units = $this->findUnits($matchField, $matchValue);
                if (count($units) !== 1) {
                    $skipped++;

                    continue;
                }

                $unit = $units[0];
                $mapped = $this->mapRowToPayload($row, $columnMap);
                $changes = $this->diffChanges($unit, $mapped);

                if ($changes === []) {
                    $noChanges++;

                    continue;
                }

                $payload = array_merge(
                    [
                        'condition' => $unit->condition,
                        'status' => $unit->status,
                    ],
                    $changes,
                );

                $result = $this->updater()($unit->id, $payload);

                if (! ($result['success'] ?? false)) {
                    $errors[] = 'Row '.($index + 1).': '.($result['message'] ?? 'Update failed.');

                    continue;
                }

                $updated++;
            }
        });

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'no_changes' => $noChanges,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, string>  $columnMap
     * @return array<string, mixed>
     */
    private function mapRowToPayload(array $row, array $columnMap): array
    {
        $payload = [];

        foreach ($columnMap as $column => $field) {
            if (! in_array($field, self::UPDATABLE_FIELDS, true) && $field !== 'id') {
                continue;
            }

            if (! array_key_exists($column, $row)) {
                continue;
            }

            $value = $row[$column];
            if ($this->isBlank($value)) {
                continue;
            }

            $payload[$field] = match ($field) {
                'status' => $this->resolveStatus($value),
                'condition' => $this->resolveCondition($value),
                'cost', 'asking_price' => AssetUnitSpreadsheetParser::parseCurrency($value),
                'id' => (int) preg_replace('/\D/', '', (string) $value),
                default => $value,
            };
        }

        return array_filter(
            $payload,
            fn ($v) => $v !== null && $v !== '',
        );
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function diffChanges(AssetUnit $unit, array $mapped): array
    {
        $changes = [];

        foreach (self::UPDATABLE_FIELDS as $field) {
            if (! array_key_exists($field, $mapped)) {
                continue;
            }

            $newValue = $mapped[$field];
            $current = $unit->{$field};

            if (in_array($field, ['cost', 'asking_price'], true)) {
                $currentFloat = $current !== null ? round((float) $current, 2) : null;
                $newFloat = $newValue !== null ? round((float) $newValue, 2) : null;
                if ($currentFloat === $newFloat) {
                    continue;
                }
            } elseif ((string) $current === (string) $newValue) {
                continue;
            }

            $changes[$field] = $newValue;
        }

        return $changes;
    }

    /**
     * @return list<AssetUnit>
     */
    private function findUnits(string $matchField, mixed $matchValue): array
    {
        if ($this->isBlank($matchValue)) {
            return [];
        }

        $query = AssetUnit::query()->with('asset:id,display_name');

        if ($matchField === 'id') {
            $id = (int) preg_replace('/\D/', '', (string) $matchValue);

            return $id > 0 ? $query->whereKey($id)->get()->all() : [];
        }

        if ($matchField === 'hin') {
            return $this->findByNormalizedField($query, 'hin', (string) $matchValue);
        }

        if ($matchField === 'serial_number') {
            return $this->findByNormalizedField($query, 'serial_number', (string) $matchValue);
        }

        return [];
    }

    /**
     * @return list<AssetUnit>
     */
    private function findByNormalizedField(Builder $query, string $column, string $value): array
    {
        $normalized = $this->normalizeIdentifier($value);
        if ($normalized === '') {
            return [];
        }

        $sql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), ' ', ''), '-', ''), '/', ''), '.', ''), ':', ''))";

        return $query
            ->whereRaw("{$sql} = ?", [$normalized])
            ->get()
            ->all();
    }

    private function normalizeIdentifier(string $value): string
    {
        $clean = strtoupper(trim($value));

        return preg_replace('/[\s\-\/\.:]/', '', $clean) ?? '';
    }

    private function normalizeMatchKey(string $matchField, mixed $matchValue): string
    {
        if ($this->isBlank($matchValue)) {
            return '';
        }

        if ($matchField === 'id') {
            return 'id:'.(int) preg_replace('/\D/', '', (string) $matchValue);
        }

        return $matchField.':'.$this->normalizeIdentifier((string) $matchValue);
    }

    private function resolveStatus(?string $value): ?int
    {
        if ($this->isBlank($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        foreach (UnitStatus::options() as $option) {
            if (
                (string) $option['id'] === $trimmed
                || strcasecmp((string) $option['name'], $trimmed) === 0
                || strcasecmp((string) $option['value'], $trimmed) === 0
            ) {
                return (int) $option['id'];
            }
        }

        return null;
    }

    private function resolveCondition(?string $value): ?int
    {
        if ($this->isBlank($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        foreach (UnitCondition::options() as $option) {
            if (
                (string) $option['id'] === $trimmed
                || strcasecmp((string) $option['name'], $trimmed) === 0
                || strcasecmp((string) $option['value'], $trimmed) === 0
            ) {
                return (int) $option['id'];
            }
        }

        return null;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private function updater(): UpdateAssetUnit
    {
        return $this->updater ?? app(UpdateAssetUnit::class);
    }
}
