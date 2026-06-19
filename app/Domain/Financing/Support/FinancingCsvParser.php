<?php

declare(strict_types=1);

namespace App\Domain\Financing\Support;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class FinancingCsvParser
{
    /** @var list<string> */
    private const HEADER_MARKERS = [
        'Serial/VIN',
        'Serial Number',
        'HIN',
        'Hull Number',
        'Original Balance',
        'Current Balance',
    ];

    /**
     * @return list<array<int, string|null>>
     */
    public function readRawRows(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! is_string($path) || ! is_readable($path)) {
            throw new RuntimeException('Unable to read CSV file.');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        $rawRows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rawRows[] = $row;
        }
        fclose($handle);

        if ($rawRows === []) {
            throw new RuntimeException('CSV file is empty.');
        }

        return $rawRows;
    }

    /**
     * @param  list<array<int, string|null>>  $rawRows
     */
    public function suggestHeaderRowIndex(array $rawRows): int
    {
        foreach ($rawRows as $index => $row) {
            $joined = implode(',', array_map('strval', $row));
            foreach (self::HEADER_MARKERS as $marker) {
                if (stripos($joined, $marker) !== false) {
                    return $index;
                }
            }
        }

        return 0;
    }

    /**
     * @param  list<array<int, string|null>>  $rawRows
     * @return array{
     *   columns: list<string>,
     *   header_row_index: int,
     *   rows: list<array<string, string|null>>,
     *   preamble: list<string>
     * }
     */
    public function parseRawRows(array $rawRows, int $headerRowIndex): array
    {
        if ($headerRowIndex < 0 || $headerRowIndex >= count($rawRows)) {
            throw new RuntimeException('Header row is out of range.');
        }

        $header = array_map(fn ($v) => trim((string) $v), $rawRows[$headerRowIndex]);
        $columns = array_values(array_filter($header, fn ($v) => $v !== ''));

        if ($columns === []) {
            throw new RuntimeException('Selected row has no column headers.');
        }

        $preamble = [];
        for ($i = 0; $i < $headerRowIndex; $i++) {
            $line = trim(implode(',', array_map('strval', $rawRows[$i])));
            if ($line !== '') {
                $preamble[] = $line;
            }
        }

        $rows = [];
        for ($i = $headerRowIndex + 1; $i < count($rawRows); $i++) {
            $cells = $rawRows[$i];
            if ($this->isEmptyRow($cells)) {
                continue;
            }

            $assoc = [];
            foreach ($header as $idx => $colName) {
                if ($colName === '') {
                    continue;
                }
                $assoc[$colName] = isset($cells[$idx]) ? trim((string) $cells[$idx]) : null;
            }

            if ($this->rowHasData($assoc)) {
                $rows[] = $assoc;
            }
        }

        return [
            'columns' => $columns,
            'header_row_index' => $headerRowIndex,
            'rows' => $rows,
            'preamble' => $preamble,
        ];
    }

    /**
     * @return array{
     *   columns: list<string>,
     *   header_row_index: int,
     *   rows: list<array<string, string|null>>,
     *   preamble: list<string>
     * }
     */
    public function parse(UploadedFile|string $file): array
    {
        $rawRows = $this->readRawRows($file);
        $headerRowIndex = $this->suggestHeaderRowIndex($rawRows);

        return $this->parseRawRows($rawRows, $headerRowIndex);
    }

    public static function normalizeMatchValue(?string $value): string
    {
        $clean = strtoupper(trim((string) $value));

        return preg_replace('/[\s\-\/\.:]/', '', $clean) ?? '';
    }

    /**
     * SQL expression that normalizes a column the same way as {@see normalizeMatchValue()}.
     */
    public static function normalizedFieldSql(string $column): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), ' ', ''), '-', ''), '/', ''), '.', ''), ':', ''))";
    }

    public static function parseCurrency(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $clean = str_replace([',', '$', '"'], '', trim($value));

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    public static function parseInteger(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $clean = str_replace([',', '"'], '', trim($value));

        return is_numeric($clean) ? (int) $clean : null;
    }

    public static function parseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 1:1 Northpoint aging report column map (see docs/Example_Data/Sample Financial Data - Sheet1.csv).
     *
     * @return array<string, string>
     */
    public static function defaultNorthpointColumnMap(): array
    {
        return [
            'Dealer Name' => 'dealer_name',
            'Dealer CIN' => 'dealer_cin',
            'Supplier Name' => 'supplier_name',
            'Supplier CIN' => 'supplier_cin',
            'Invoice Number' => 'lender_invoice_number',
            'Invoice Date' => 'financed_at',
            'Aging (Days)' => 'aging_days',
            'Interest Start Date' => 'interest_start_date',
            'Status' => 'lender_status',
            'Model Year' => 'model_year',
            'Model Number' => 'model_number',
            'Serial/VIN' => 'serial_vin',
            'Original Balance' => 'principal_amount',
            'Current Balance' => 'current_balance',
            'Curtailment Current Due' => 'curtailment_current_due',
            'Past Due Curtailment' => 'past_due_curtailment',
        ];
    }

    /**
     * Match column aliases for asset unit lookup (stored separately from financing fields).
     *
     * @return list<string>
     */
    public static function defaultMatchColumns(): array
    {
        return ['Serial/VIN', 'Serial Number', 'HIN', 'Hull Number'];
    }

    /**
     * Financing fields available for CSV column mapping in the import UI.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function importFieldOptions(): array
    {
        return [
            ['value' => 'match_key', 'label' => 'Match key (asset unit lookup only)'],
            ['value' => 'dealer_name', 'label' => 'Dealer Name'],
            ['value' => 'dealer_cin', 'label' => 'Dealer CIN'],
            ['value' => 'supplier_name', 'label' => 'Supplier Name'],
            ['value' => 'supplier_cin', 'label' => 'Supplier CIN'],
            ['value' => 'lender_invoice_number', 'label' => 'Invoice Number'],
            ['value' => 'financed_at', 'label' => 'Invoice Date'],
            ['value' => 'aging_days', 'label' => 'Aging (Days)'],
            ['value' => 'interest_start_date', 'label' => 'Interest Start Date'],
            ['value' => 'lender_status', 'label' => 'Status'],
            ['value' => 'model_year', 'label' => 'Model Year'],
            ['value' => 'model_number', 'label' => 'Model Number'],
            ['value' => 'serial_vin', 'label' => 'Serial/VIN'],
            ['value' => 'principal_amount', 'label' => 'Original Balance'],
            ['value' => 'current_balance', 'label' => 'Current Balance'],
            ['value' => 'curtailment_current_due', 'label' => 'Curtailment Current Due'],
            ['value' => 'past_due_curtailment', 'label' => 'Past Due Curtailment'],
        ];
    }

    /**
     * @param  array<int, string|null>  $cells
     */
    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function rowHasData(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }
}
