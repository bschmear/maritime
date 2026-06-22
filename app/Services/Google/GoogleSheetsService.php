<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Domain\Integration\Models\Integration;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\BooleanCondition;
use Google\Service\Sheets\CellData;
use Google\Service\Sheets\ConditionValue;
use Google\Service\Sheets\DataValidationRule;
use Google\Service\Sheets\GridRange;
use Google\Service\Sheets\Request;
use Google\Service\Sheets\RowData;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\ValueRange;

class GoogleSheetsService
{
    public function __construct(
        private readonly GoogleOAuthService $oauth,
    ) {}

    public function getSpreadsheet(Integration $integration, string $spreadsheetId): Spreadsheet
    {
        return $this->sheets($integration)->spreadsheets->get($spreadsheetId);
    }

    /**
     * @param  list<list<mixed>>  $values
     */
    public function writeRange(
        Integration $integration,
        string $spreadsheetId,
        string $range,
        array $values,
    ): void {
        $body = new ValueRange(['values' => $this->normalizeSheetValues($values)]);
        $this->sheets($integration)->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => 'USER_ENTERED'],
        );
    }

    /**
     * Google Sheets requires each row as a JSON array. Sparse PHP arrays (e.g. after
     * null cells are omitted during serialization) become JSON objects and trigger 400 errors.
     *
     * @param  list<list<mixed>>  $values
     * @return list<list<string>>
     */
    private function normalizeSheetValues(array $values): array
    {
        return array_map(function (array $row): array {
            return array_map(function (mixed $cell): string {
                if ($cell === null) {
                    return '';
                }

                if (is_bool($cell)) {
                    return $cell ? 'TRUE' : 'FALSE';
                }

                if (is_scalar($cell)) {
                    return (string) $cell;
                }

                return '';
            }, array_values($row));
        }, $values);
    }

    /**
     * @return list<list<mixed>>
     */
    public function readRange(Integration $integration, string $spreadsheetId, string $range): array
    {
        $response = $this->sheets($integration)->spreadsheets_values->get($spreadsheetId, $range);

        return $response->getValues() ?? [];
    }

    /**
     * @param  list<string>  $sheetTitles
     */
    public function ensureSheets(Integration $integration, string $spreadsheetId, array $sheetTitles): void
    {
        $spreadsheet = $this->getSpreadsheet($integration, $spreadsheetId);
        $byTitle = $this->sheetTitlesToIds($spreadsheet);

        $requests = [];
        $primaryTitle = $sheetTitles[0] ?? null;

        if ($primaryTitle !== null && ! array_key_exists($primaryTitle, $byTitle)) {
            $defaultSheetId = $this->defaultSheetIdForRename($byTitle);
            if ($defaultSheetId !== null) {
                $requests[] = new Request([
                    'updateSheetProperties' => [
                        'properties' => new SheetProperties([
                            'sheetId' => $defaultSheetId,
                            'title' => $primaryTitle,
                        ]),
                        'fields' => 'title',
                    ],
                ]);
                $byTitle = $this->applyRename($byTitle, $defaultSheetId, $primaryTitle);
            }
        }

        foreach ($sheetTitles as $title) {
            if (! array_key_exists($title, $byTitle)) {
                $requests[] = new Request([
                    'addSheet' => [
                        'properties' => new SheetProperties(['title' => $title]),
                    ],
                ]);
            }
        }

        if ($primaryTitle !== null && array_key_exists($primaryTitle, $byTitle) && count($byTitle) > 1) {
            foreach ($this->defaultSheetTitles() as $defaultTitle) {
                if ($defaultTitle === $primaryTitle || ! isset($byTitle[$defaultTitle])) {
                    continue;
                }

                $requests[] = new Request([
                    'deleteSheet' => ['sheetId' => $byTitle[$defaultTitle]],
                ]);
            }
        }

        $this->batchUpdate($integration, $spreadsheetId, $requests);
    }

    /**
     * @return array<string, int>
     */
    private function sheetTitlesToIds(Spreadsheet $spreadsheet): array
    {
        $map = [];
        foreach ($spreadsheet->getSheets() ?? [] as $sheet) {
            $properties = $sheet->getProperties();
            $title = $properties?->getTitle();
            $sheetId = $properties?->getSheetId();
            if ($title !== null && $sheetId !== null) {
                $map[$title] = $sheetId;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function defaultSheetTitles(): array
    {
        return ['Sheet1', 'Sheet 1'];
    }

    /**
     * @param  array<string, int>  $byTitle
     */
    private function defaultSheetIdForRename(array $byTitle): ?int
    {
        foreach ($this->defaultSheetTitles() as $title) {
            if (isset($byTitle[$title])) {
                return $byTitle[$title];
            }
        }

        if (count($byTitle) === 1) {
            return array_values($byTitle)[0];
        }

        return null;
    }

    /**
     * @param  array<string, int>  $byTitle
     * @return array<string, int>
     */
    private function applyRename(array $byTitle, int $sheetId, string $newTitle): array
    {
        foreach ($byTitle as $title => $id) {
            if ($id === $sheetId) {
                unset($byTitle[$title]);
                break;
            }
        }

        $byTitle[$newTitle] = $sheetId;

        return $byTitle;
    }

    /**
     * @param  list<Request>  $requests
     */
    public function batchUpdate(Integration $integration, string $spreadsheetId, array $requests): void
    {
        if ($requests === []) {
            return;
        }

        $batch = new BatchUpdateSpreadsheetRequest(['requests' => $requests]);
        $this->sheets($integration)->spreadsheets->batchUpdate($spreadsheetId, $batch);
    }

    public function applyListValidationFromReference(
        Integration $integration,
        string $spreadsheetId,
        int $sheetId,
        int $startRow,
        int $endRow,
        int $columnIndex,
        string $referenceRange,
    ): void {
        $gridRange = new GridRange([
            'sheetId' => $sheetId,
            'startRowIndex' => $startRow,
            'endRowIndex' => $endRow,
            'startColumnIndex' => $columnIndex,
            'endColumnIndex' => $columnIndex + 1,
        ]);

        $condition = new BooleanCondition([
            'type' => 'ONE_OF_RANGE',
            'values' => [
                new ConditionValue(['userEnteredValue' => '='.$referenceRange]),
            ],
        ]);

        $rule = new DataValidationRule([
            'condition' => $condition,
            'showCustomUi' => true,
            'strict' => false,
        ]);

        $cell = new CellData(['dataValidation' => $rule]);
        $row = new RowData(['values' => [$cell]]);

        $request = new Request([
            'repeatCell' => [
                'range' => $gridRange,
                'cell' => $cell,
                'fields' => 'dataValidation',
            ],
        ]);

        $this->batchUpdate($integration, $spreadsheetId, [$request]);
    }

    public function hideSheet(Integration $integration, string $spreadsheetId, string $sheetTitle): void
    {
        $spreadsheet = $this->getSpreadsheet($integration, $spreadsheetId);
        $sheetId = null;
        foreach ($spreadsheet->getSheets() ?? [] as $sheet) {
            if ($sheet->getProperties()?->getTitle() === $sheetTitle) {
                $sheetId = $sheet->getProperties()?->getSheetId();
                break;
            }
        }

        if ($sheetId === null) {
            return;
        }

        $request = new Request([
            'updateSheetProperties' => [
                'properties' => new SheetProperties([
                    'sheetId' => $sheetId,
                    'hidden' => true,
                ]),
                'fields' => 'hidden',
            ],
        ]);

        $this->batchUpdate($integration, $spreadsheetId, [$request]);
    }

    public function sheetId(Spreadsheet $spreadsheet, string $title): ?int
    {
        foreach ($spreadsheet->getSheets() ?? [] as $sheet) {
            if ($sheet->getProperties()?->getTitle() === $title) {
                return $sheet->getProperties()?->getSheetId();
            }
        }

        return null;
    }

    private function sheets(Integration $integration): Sheets
    {
        $client = $this->oauth->clientForIntegration($integration);

        return new Sheets($client);
    }
}
