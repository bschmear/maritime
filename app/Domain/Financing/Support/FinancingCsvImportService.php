<?php

declare(strict_types=1);

namespace App\Domain\Financing\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Bill\Models\Bill;
use App\Domain\Financing\Models\Financing;
use App\Enums\Financing\BillType;
use App\Enums\Financing\Status;
use App\Models\AccountSettings;
use Illuminate\Support\Facades\DB;

class FinancingCsvImportService
{
    public function __construct(
        private readonly FinancingCsvParser $parser = new FinancingCsvParser,
    ) {}

    /**
     * @param  list<array<string, string|null>>  $rows
     * @param  array<string, string>  $columnMap
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   summary: array{matched: int, unlinked: int, ambiguous: int, skipped: int}
     * }
     */
    public function preview(
        array $rows,
        string $matchColumn,
        string $assetUnitMatchField,
        array $columnMap = [],
        ?int $vendorId = null,
    ): array {
        $columnMap = $columnMap !== [] ? $columnMap : FinancingCsvParser::defaultNorthpointColumnMap();
        $seenKeys = [];
        $previewRows = [];
        $matched = 0;
        $ambiguous = 0;
        $skipped = 0;

        foreach ($rows as $index => $row) {
            $matchValue = $row[$matchColumn] ?? null;
            $normalized = FinancingCsvParser::normalizeMatchValue($matchValue);
            $units = $this->findAssetUnits($assetUnitMatchField, $normalized);
            $mapped = $this->mapRowToFinancingPayload($row, $columnMap, $matchValue);

            $status = 'unmatched';
            $assetUnit = null;

            if ($normalized === '') {
                $status = 'unmatched';
            } elseif (count($units) > 1) {
                $status = 'ambiguous';
                $ambiguous++;
            } elseif (count($units) === 1) {
                $status = 'matched';
                $assetUnit = $units[0];
                $matched++;
            } else {
                // Will import without a linked asset unit when no unique match is found.
            }

            if ($normalized !== '' && isset($seenKeys[$normalized])) {
                $status = 'duplicate';
            }
            if ($normalized !== '') {
                $seenKeys[$normalized] = true;
            }

            $action = $this->resolveImportAction($mapped, $matchValue);

            if ($action === 'skip') {
                $skipped++;
            }

            $existing = $this->findExistingFinancingForImport(
                $assetUnit?->id,
                $vendorId,
                $mapped['lender_invoice_number'] ?? null,
                $mapped['serial_vin'] ?? (is_string($matchValue) ? trim($matchValue) : null),
            );

            if ($existing !== null && $action !== 'skip') {
                $action = 'update';
            } elseif ($action === 'create') {
                $action = $assetUnit !== null ? 'create' : 'create_unlinked';
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
                'mapped' => $mapped,
            ];
        }

        return [
            'rows' => $previewRows,
            'summary' => [
                'matched' => $matched,
                'unlinked' => count(array_filter(
                    $previewRows,
                    fn (array $row): bool => $row['action'] !== 'skip' && $row['asset_unit'] === null,
                )),
                'ambiguous' => $ambiguous,
                'skipped' => $skipped,
            ],
        ];
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @param  array<string, string>  $columnMap
     * @return array{
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   linked: int,
     *   unlinked: int,
     *   errors: list<string>,
     *   unlinked_financings: list<array<string, mixed>>
     * }
     */
    public function import(
        array $rows,
        string $matchColumn,
        string $assetUnitMatchField,
        int $vendorId,
        array $columnMap = [],
    ): array {
        $columnMap = $columnMap !== [] ? $columnMap : FinancingCsvParser::defaultNorthpointColumnMap();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $linked = 0;
        $unlinked = 0;
        $errors = [];
        $unlinkedFinancings = [];

        DB::transaction(function () use (
            $rows,
            $matchColumn,
            $assetUnitMatchField,
            $vendorId,
            $columnMap,
            &$created,
            &$updated,
            &$skipped,
            &$linked,
            &$unlinked,
            &$errors,
            &$unlinkedFinancings,
        ) {
            foreach ($rows as $index => $row) {
                $matchValue = $row[$matchColumn] ?? null;
                $normalized = FinancingCsvParser::normalizeMatchValue($matchValue);
                $payload = $this->mapRowToFinancingPayload($row, $columnMap, $matchValue);
                $payload['vendor_id'] = $vendorId;

                $units = $this->findAssetUnits($assetUnitMatchField, $normalized);
                $assetUnit = count($units) === 1 ? $units[0] : null;
                $linkStatus = $assetUnit !== null
                    ? 'matched'
                    : (count($units) > 1 ? 'ambiguous' : 'unmatched');

                $action = $this->resolveImportAction($payload, $matchValue);
                if ($action === 'skip') {
                    $skipped++;

                    continue;
                }

                if ($assetUnit !== null) {
                    $payload['asset_unit_id'] = $assetUnit->id;
                }

                $balance = (float) ($payload['current_balance'] ?? 0);
                $payload['status'] = $balance <= 0 ? Status::PaidOff->value : Status::Active->value;
                $payload['last_imported_at'] = now();

                $financing = $this->findExistingFinancingForImport(
                    $assetUnit?->id,
                    $vendorId,
                    $payload['lender_invoice_number'] ?? null,
                    $payload['serial_vin'] ?? (is_string($matchValue) ? trim($matchValue) : null),
                );

                if ($financing === null) {
                    $financing = Financing::query()->create($payload);
                    $created++;
                } else {
                    $financing->update($payload);
                    $updated++;
                }

                if ($assetUnit !== null) {
                    $linked++;
                    AssetUnit::query()
                        ->whereKey($assetUnit->id)
                        ->update(['is_financed' => $payload['status'] === Status::Active->value]);
                } elseif ($financing->asset_unit_id === null) {
                    $unlinked++;
                    $unlinkedFinancings[] = $this->formatUnlinkedFinancing($financing, $matchValue, $linkStatus);
                }

                if (count($units) > 1) {
                    $errors[] = 'Row '.($index + 1).": imported without asset unit — multiple matches for {$matchValue}.";
                }

                $this->upsertBillFromRow($financing, $row, $columnMap, $vendorId);
            }
        });

        AccountSettings::getCurrent()->update([
            'financing_csv_column_map' => $columnMap,
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'linked' => $linked,
            'unlinked' => $unlinked,
            'errors' => $errors,
            'unlinked_financings' => $unlinkedFinancings,
        ];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveImportAction(array $mapped, ?string $matchValue): string
    {
        $normalized = FinancingCsvParser::normalizeMatchValue($matchValue);
        $hasIdentifier = $normalized !== ''
            || ! empty($mapped['lender_invoice_number'])
            || ! empty($mapped['serial_vin']);

        if (! $hasIdentifier) {
            return 'skip';
        }

        return 'create';
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUnlinkedFinancing(Financing $financing, ?string $matchValue, string $linkStatus): array
    {
        return [
            'id' => $financing->id,
            'display_name' => $financing->display_name,
            'serial_vin' => $financing->serial_vin,
            'match_value' => $matchValue,
            'lender_invoice_number' => $financing->lender_invoice_number,
            'model_number' => $financing->model_number,
            'current_balance' => $financing->current_balance,
            'reason' => $linkStatus === 'ambiguous' ? 'ambiguous' : 'unmatched',
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, string>  $columnMap
     * @return array<string, mixed>
     */
    private function mapRowToFinancingPayload(array $row, array $columnMap, ?string $matchValue = null): array
    {
        $payload = [];

        foreach ($columnMap as $csvColumn => $field) {
            if ($field === 'match_key' || ! array_key_exists($csvColumn, $row)) {
                continue;
            }

            $raw = $row[$csvColumn];

            $payload[$field] = match ($field) {
                'principal_amount', 'current_balance', 'curtailment_current_due', 'past_due_curtailment' => FinancingCsvParser::parseCurrency($raw),
                'aging_days' => FinancingCsvParser::parseInteger($raw),
                'financed_at', 'interest_start_date' => FinancingCsvParser::parseDate($raw),
                default => $raw !== null && trim($raw) !== '' ? trim($raw) : null,
            };
        }

        if (! isset($payload['serial_vin']) && $matchValue !== null && trim($matchValue) !== '') {
            $payload['serial_vin'] = trim($matchValue);
        }

        if (! isset($payload['interest_start_date']) && isset($payload['financed_at'])) {
            $payload['interest_start_date'] = $payload['financed_at'];
        }

        return array_filter($payload, fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, string>  $columnMap
     */
    private function extractMappedValue(array $row, array $columnMap, string $targetField): ?string
    {
        foreach ($columnMap as $csvColumn => $field) {
            if ($field === $targetField) {
                $raw = $row[$csvColumn] ?? null;

                return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
            }
        }

        return null;
    }

    private function findExistingFinancingForImport(
        ?int $assetUnitId,
        ?int $vendorId,
        ?string $invoiceNumber,
        ?string $serialVin,
    ): ?Financing {
        if ($assetUnitId !== null) {
            return $this->findExistingFinancing($assetUnitId, $vendorId, $invoiceNumber, $serialVin);
        }

        if ($vendorId === null) {
            return null;
        }

        $query = Financing::query()
            ->where('vendor_id', $vendorId)
            ->whereNull('asset_unit_id');

        if (is_string($invoiceNumber) && $invoiceNumber !== '') {
            $query->where('lender_invoice_number', $invoiceNumber);
        } elseif (is_string($serialVin) && trim($serialVin) !== '') {
            $query->where('serial_vin', trim($serialVin));
        } else {
            return null;
        }

        return $query->orderByDesc('id')->first();
    }

    private function findExistingFinancing(
        int $assetUnitId,
        ?int $vendorId,
        ?string $invoiceNumber,
        ?string $serialVin,
    ): ?Financing {
        $query = Financing::query()->where('asset_unit_id', $assetUnitId);

        if ($vendorId !== null) {
            $query->where('vendor_id', $vendorId);
        }

        if (is_string($invoiceNumber) && $invoiceNumber !== '') {
            $query->where('lender_invoice_number', $invoiceNumber);
        } elseif (is_string($serialVin) && $serialVin !== '') {
            $query->where('serial_vin', trim($serialVin));
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, string>  $columnMap
     */
    private function upsertBillFromRow(Financing $financing, array $row, array $columnMap, int $vendorId): void
    {
        $invoiceColumn = array_search('lender_invoice_number', $columnMap, true);
        $invoiceNumber = $invoiceColumn !== false ? ($row[$invoiceColumn] ?? null) : $financing->lender_invoice_number;

        if (! is_string($invoiceNumber) || trim($invoiceNumber) === '') {
            return;
        }

        $invoiceNumber = trim($invoiceNumber);
        $amount = (float) ($financing->principal_amount ?? $financing->current_balance ?? 0);
        $balance = (float) ($financing->current_balance ?? 0);

        Bill::query()->updateOrCreate(
            [
                'financing_id' => $financing->id,
                'doc_number' => $invoiceNumber,
            ],
            [
                'vendor_id' => $vendorId,
                'financing_bill_type' => BillType::Interest->value,
                'txn_date' => $financing->financed_at,
                'due_date' => $financing->next_payment_date,
                'total_amt' => $amount,
                'balance' => $balance,
                'private_note' => 'Imported from lender aging report',
                'status' => $balance > 0 ? 'open' : 'paid',
            ],
        );
    }

    /**
     * @return list<AssetUnit>
     */
    private function findAssetUnits(string $field, string $normalized): array
    {
        if (! in_array($field, ['hin', 'serial_number'], true) || $normalized === '') {
            return [];
        }

        // CSV "Serial/VIN" values may be stored on either column; try primary then fallback.
        $columns = $field === 'hin'
            ? ['hin', 'serial_number']
            : ['serial_number', 'hin'];

        foreach ($columns as $column) {
            $units = AssetUnit::query()
                ->whereRaw(
                    FinancingCsvParser::normalizedFieldSql($column).' = ?',
                    [$normalized],
                )
                ->limit(5)
                ->get()
                ->all();

            if ($units !== []) {
                return $units;
            }
        }

        return [];
    }
}
