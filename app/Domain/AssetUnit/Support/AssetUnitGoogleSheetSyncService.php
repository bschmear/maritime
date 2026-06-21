<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\GoogleIntegrationSettings;
use App\Services\Google\GoogleDriveService;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\GoogleSheetsService;
use Illuminate\Support\Collection;
use RuntimeException;

class AssetUnitGoogleSheetSyncService
{
    public function __construct(
        private readonly GoogleOAuthService $oauth,
        private readonly GoogleDriveService $drive,
        private readonly GoogleSheetsService $sheets,
        private readonly AssetUnitGoogleSheetBuilder $builder = new AssetUnitGoogleSheetBuilder,
        private readonly AssetUnitGoogleSheetImportService $importer = new AssetUnitGoogleSheetImportService,
    ) {}

    /**
     * @return array{
     *   spreadsheet_id: string,
     *   spreadsheet_url: string,
     *   recreated: bool,
     *   row_count: int,
     *   message?: string
     * }
     */
    public function push(?Integration $integration = null): array
    {
        $integration = $this->requireIntegration($integration);
        $settings = GoogleIntegrationSettings::from($integration);

        [$spreadsheetId, $recreated, $message] = $this->ensureInventorySpreadsheet($integration, $settings);

        $inventorySheet = $settings->inventorySheetName();
        $referenceSheet = GoogleIntegrationSettings::REFERENCE_SHEET_NAME;

        $this->sheets->ensureSheets($integration, $spreadsheetId, [$inventorySheet, $referenceSheet]);

        $units = $this->loadUnits();
        $inventoryRows = $this->builder->buildInventoryRows($units);
        $reference = $this->builder->referenceLists();

        $this->sheets->writeRange(
            $integration,
            $spreadsheetId,
            $inventorySheet.'!A1',
            $inventoryRows,
        );

        $referenceRows = [
            ['Status', 'Condition', 'Make', 'Variant'],
        ];
        $maxRef = max(
            count($reference['status']),
            count($reference['condition']),
            count($reference['makes']),
            count($reference['variants']),
            1,
        );
        for ($i = 0; $i < $maxRef; $i++) {
            $referenceRows[] = [
                $reference['status'][$i] ?? '',
                $reference['condition'][$i] ?? '',
                $reference['makes'][$i] ?? '',
                $reference['variants'][$i] ?? '',
            ];
        }

        $this->sheets->writeRange(
            $integration,
            $spreadsheetId,
            $referenceSheet.'!A1',
            $referenceRows,
        );

        $this->applyValidations($integration, $spreadsheetId, $inventorySheet, $referenceSheet, count($inventoryRows));
        $this->sheets->hideSheet($integration, $spreadsheetId, $referenceSheet);

        $settings->mergeIntoIntegration($integration, [
            'inventory_spreadsheet_id' => $spreadsheetId,
            'inventory_sheet_name' => $inventorySheet,
            'last_pushed_at' => now()->toIso8601String(),
        ]);

        return [
            'spreadsheet_id' => $spreadsheetId,
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/'.$spreadsheetId,
            'recreated' => $recreated,
            'row_count' => max(0, count($inventoryRows) - 1),
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pull(?Integration $integration = null): array
    {
        $integration = $this->requireIntegration($integration);
        $settings = GoogleIntegrationSettings::from($integration);

        [$spreadsheetId, $recreated] = $this->ensureInventorySpreadsheet($integration, $settings);

        if ($recreated) {
            $this->push($integration);

            return [
                'updated' => 0,
                'skipped' => 0,
                'no_changes' => 0,
                'errors' => [],
                'rows' => [],
                'recreated' => true,
                'message' => 'The inventory sheet was missing and has been recreated from Helmful. Edit the new sheet, then import again.',
            ];
        }

        $sheetName = $settings->inventorySheetName();
        $rows = $this->sheets->readRange($integration, $spreadsheetId, $sheetName.'!A:ZZ');

        $result = $this->importer->import($rows);
        $result['recreated'] = false;
        $result['spreadsheet_url'] = 'https://docs.google.com/spreadsheets/d/'.$spreadsheetId;

        $settings->mergeIntoIntegration($integration, [
            'last_pulled_at' => now()->toIso8601String(),
        ]);

        return $result;
    }

    /**
     * @return array{spreadsheet_id: string, spreadsheet_url: string}
     */
    public function recreate(?Integration $integration = null): array
    {
        $integration = $this->requireIntegration($integration);
        $settings = GoogleIntegrationSettings::from($integration);

        $settings->mergeIntoIntegration($integration, [
            'inventory_spreadsheet_id' => null,
        ]);
        $integration->refresh();

        $push = $this->push($integration);

        return [
            'spreadsheet_id' => $push['spreadsheet_id'],
            'spreadsheet_url' => $push['spreadsheet_url'],
        ];
    }

    private function requireIntegration(?Integration $integration): Integration
    {
        $integration ??= $this->oauth->integration();

        if ($integration === null || ! $this->oauth->hasCredentials()) {
            throw new RuntimeException('Connect Google under Integrations before syncing inventory.');
        }

        return $integration;
    }

    /**
     * @return array{0: string, 1: bool, 2: ?string}
     */
    private function ensureInventorySpreadsheet(Integration $integration, GoogleIntegrationSettings $settings): array
    {
        $folderId = $this->drive->ensureAppFolder($integration);
        $spreadsheetId = $settings->inventorySpreadsheetId();
        $recreated = false;
        $message = null;

        if ($spreadsheetId !== null && ! $this->drive->fileExists($integration, $spreadsheetId)) {
            $spreadsheetId = null;
            $recreated = true;
            $message = 'Previous inventory sheet was deleted. A new sheet has been created.';
        }

        if ($spreadsheetId === null) {
            $title = 'Helmful Inventory — '.now()->format('Y-m-d');
            $spreadsheetId = $this->drive->createSpreadsheet($integration, $title, $folderId);
            $recreated = true;
            $message ??= 'Inventory sheet created.';
        }

        return [$spreadsheetId, $recreated, $message];
    }

    private function applyValidations(
        Integration $integration,
        string $spreadsheetId,
        string $inventorySheet,
        string $referenceSheet,
        int $rowCount,
    ): void {
        $spreadsheet = $this->sheets->getSpreadsheet($integration, $spreadsheetId);
        $sheetId = $this->sheets->sheetId($spreadsheet, $inventorySheet);
        if ($sheetId === null) {
            return;
        }

        $headers = $this->builder->headers();
        $statusCol = array_search(AssetUnitGoogleSheetColumnRegistry::HEADER_STATUS, $headers, true);
        $conditionCol = array_search(AssetUnitGoogleSheetColumnRegistry::HEADER_CONDITION, $headers, true);
        $makeCol = array_search(AssetUnitGoogleSheetColumnRegistry::HEADER_MAKE, $headers, true);
        $variantCol = array_search(AssetUnitGoogleSheetColumnRegistry::HEADER_VARIANT, $headers, true);

        $endRow = max($rowCount, 500);

        if ($statusCol !== false) {
            $this->sheets->applyListValidationFromReference(
                $integration,
                $spreadsheetId,
                $sheetId,
                1,
                $endRow,
                (int) $statusCol,
                "'{$referenceSheet}'!A2:A",
            );
        }

        if ($conditionCol !== false) {
            $this->sheets->applyListValidationFromReference(
                $integration,
                $spreadsheetId,
                $sheetId,
                1,
                $endRow,
                (int) $conditionCol,
                "'{$referenceSheet}'!B2:B",
            );
        }

        if ($makeCol !== false) {
            $this->sheets->applyListValidationFromReference(
                $integration,
                $spreadsheetId,
                $sheetId,
                1,
                $endRow,
                (int) $makeCol,
                "'{$referenceSheet}'!C2:C",
            );
        }

        if ($variantCol !== false) {
            $this->sheets->applyListValidationFromReference(
                $integration,
                $spreadsheetId,
                $sheetId,
                1,
                $endRow,
                (int) $variantCol,
                "'{$referenceSheet}'!D2:D",
            );
        }
    }

    /**
     * @return Collection<int, AssetUnit>
     */
    private function loadUnits()
    {
        return AssetUnit::query()
            ->with([
                'asset.make',
                'assetVariant',
                'location:id,display_name',
            ])
            ->orderBy('id')
            ->get();
    }
}
