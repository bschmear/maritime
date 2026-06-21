<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class AssetUnitSpreadsheetParser
{
    /** @var list<string> */
    private const HEADER_MARKERS = [
        'ID',
        'Unit ID',
        'Serial Number',
        'HIN',
        'Hull ID',
        'Status',
        'Condition',
        'Cost',
        'Asking Price',
    ];

    /**
     * @return list<array<int, string|null>>
     */
    public function readRawRows(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! is_string($path) || ! is_readable($path)) {
            throw new RuntimeException('Unable to read spreadsheet file.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->readSpreadsheetRows($path);
        }

        return $this->readCsvRows($path);
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
     * Default column map for exports and round-trip imports.
     *
     * @return array<string, string>
     */
    public static function defaultColumnMap(): array
    {
        return [
            'ID' => 'id',
            'Unit ID' => 'id',
            'Asset' => 'asset',
            'Serial Number' => 'serial_number',
            'HIN' => 'hin',
            'Hull ID (HIN)' => 'hin',
            'SKU' => 'sku',
            'Status' => 'status',
            'Condition' => 'condition',
            'Cost' => 'cost',
            'Asking Price' => 'asking_price',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultMatchColumns(): array
    {
        return ['ID', 'Unit ID', 'HIN', 'Hull ID (HIN)', 'Serial Number'];
    }

    /**
     * Fields available for column mapping in the import UI.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function importFieldOptions(): array
    {
        return [
            ['value' => 'match_key', 'label' => 'Match key (unit lookup only)'],
            ['value' => 'id', 'label' => 'Unit ID'],
            ['value' => 'asset', 'label' => 'Asset (read-only)'],
            ['value' => 'serial_number', 'label' => 'Serial Number (read-only)'],
            ['value' => 'hin', 'label' => 'HIN (read-only)'],
            ['value' => 'sku', 'label' => 'SKU (read-only)'],
            ['value' => 'status', 'label' => 'Status'],
            ['value' => 'condition', 'label' => 'Condition'],
            ['value' => 'cost', 'label' => 'Cost'],
            ['value' => 'asking_price', 'label' => 'Asking Price'],
        ];
    }

    public static function parseCurrency(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $clean = str_replace([',', '$', '"'], '', trim($value));

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    /**
     * @return list<array<int, string|null>>
     */
    private function readCsvRows(string $path): array
    {
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
     * @return list<array<int, string|null>>
     */
    private function readSpreadsheetRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = trim((string) $cell->getFormattedValue());
            }
            $rawRows[] = $cells;
        }

        if ($rawRows === []) {
            throw new RuntimeException('Spreadsheet file is empty.');
        }

        return $rawRows;
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
