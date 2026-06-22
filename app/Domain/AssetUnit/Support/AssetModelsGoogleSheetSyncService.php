<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support;

use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\GoogleIntegrationSettings;
use App\Services\Google\GoogleDriveService;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\GoogleSheetsService;
use RuntimeException;

class AssetModelsGoogleSheetSyncService
{
    public function __construct(
        private readonly GoogleOAuthService $oauth,
        private readonly GoogleDriveService $drive,
        private readonly GoogleSheetsService $sheets,
        private readonly AssetModelsGoogleSheetBuilder $builder = new AssetModelsGoogleSheetBuilder,
        private readonly AssetModelsGoogleSheetImportService $importer = new AssetModelsGoogleSheetImportService,
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
        $settings->restoreModelsSpreadsheetLink($integration);
        $integration->refresh();

        [$spreadsheetId, $recreated, $message] = $this->ensureModelsSpreadsheet($integration, $settings);

        $modelsSheet = $settings->modelsSheetName();
        $referenceSheet = GoogleIntegrationSettings::REFERENCE_SHEET_NAME;

        $this->sheets->ensureSheets($integration, $spreadsheetId, [$modelsSheet, $referenceSheet]);

        $modelRows = $this->builder->buildModelRows();
        $reference = $this->builder->referenceLists();

        $this->sheets->writeRange(
            $integration,
            $spreadsheetId,
            $modelsSheet.'!A1',
            $modelRows,
        );

        $referenceRows = [
            ['Make', 'Variant', 'Hull Type', 'Hull Material', 'Boat Type'],
        ];
        $maxRef = max(
            count($reference['makes']),
            count($reference['variants']),
            count($reference['hull_types']),
            count($reference['hull_materials']),
            count($reference['boat_types']),
            1,
        );
        for ($i = 0; $i < $maxRef; $i++) {
            $referenceRows[] = [
                $reference['makes'][$i] ?? '',
                $reference['variants'][$i] ?? '',
                $reference['hull_types'][$i] ?? '',
                $reference['hull_materials'][$i] ?? '',
                $reference['boat_types'][$i] ?? '',
            ];
        }

        $this->sheets->writeRange(
            $integration,
            $spreadsheetId,
            $referenceSheet.'!A1',
            $referenceRows,
        );

        $this->applyValidations($integration, $spreadsheetId, $modelsSheet, $referenceSheet, count($modelRows));
        $this->sheets->hideSheet($integration, $spreadsheetId, $referenceSheet);

        $settings->persistModelsSpreadsheet(
            $integration,
            $spreadsheetId,
            $modelsSheet,
            touchSyncTimestamps: true,
        );

        return [
            'spreadsheet_id' => $spreadsheetId,
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/'.$spreadsheetId,
            'recreated' => $recreated,
            'row_count' => max(0, count($modelRows) - 1),
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
        $settings->restoreModelsSpreadsheetLink($integration);
        $integration->refresh();

        [$spreadsheetId, $recreated] = $this->ensureModelsSpreadsheet($integration, $settings);

        if ($recreated) {
            $this->push($integration);

            return [
                'updated' => 0,
                'skipped' => 0,
                'no_changes' => 0,
                'errors' => [],
                'rows' => [],
                'recreated' => true,
                'message' => 'The models sheet was missing and has been recreated from Helmful. Edit the new sheet, then import again.',
            ];
        }

        $sheetName = $settings->modelsSheetName();
        $rows = $this->sheets->readRange($integration, $spreadsheetId, $sheetName.'!A:ZZ');

        $result = $this->importer->import($rows);
        $result['recreated'] = false;
        $result['spreadsheet_url'] = 'https://docs.google.com/spreadsheets/d/'.$spreadsheetId;

        $settings->mergeIntoIntegration($integration, [
            'last_models_pulled_at' => now()->toIso8601String(),
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

        $settings->clearModelsSpreadsheet($integration);
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
            throw new RuntimeException('Connect Google under Integrations before syncing models.');
        }

        return $integration;
    }

    /**
     * @return array{0: string, 1: bool, 2: ?string}
     */
    private function ensureModelsSpreadsheet(Integration $integration, GoogleIntegrationSettings $settings): array
    {
        $folderId = $this->drive->ensureAppFolder($integration);
        $spreadsheetId = $settings->modelsSpreadsheetId();
        $recreated = false;
        $message = null;

        if ($spreadsheetId !== null && ! $this->drive->fileExists($integration, $spreadsheetId)) {
            $settings->clearModelsSpreadsheet($integration);
            $integration->refresh();
            $spreadsheetId = null;
            $recreated = true;
            $message = 'Previous models sheet was deleted. A new sheet has been created.';
        }

        if ($spreadsheetId === null) {
            $title = 'Helmful Models';
            $spreadsheetId = $this->drive->createSpreadsheet($integration, $title, $folderId);
            $settings->persistModelsSpreadsheet(
                $integration,
                $spreadsheetId,
                $settings->modelsSheetName(),
            );
            $integration->refresh();
            $recreated = true;
            $message ??= 'Models sheet created.';
        }

        return [$spreadsheetId, $recreated, $message];
    }

    private function applyValidations(
        Integration $integration,
        string $spreadsheetId,
        string $modelsSheet,
        string $referenceSheet,
        int $rowCount,
    ): void {
        $spreadsheet = $this->sheets->getSpreadsheet($integration, $spreadsheetId);
        $sheetId = $this->sheets->sheetId($spreadsheet, $modelsSheet);
        if ($sheetId === null) {
            return;
        }

        $headers = $this->builder->headers();
        $makeCol = array_search(AssetModelsGoogleSheetColumnRegistry::HEADER_MAKE, $headers, true);
        $variantCol = array_search(AssetModelsGoogleSheetColumnRegistry::HEADER_VARIANT, $headers, true);
        $hullTypeCol = array_search(AssetModelsGoogleSheetColumnRegistry::HEADER_HULL_TYPE, $headers, true);
        $hullMaterialCol = array_search(AssetModelsGoogleSheetColumnRegistry::HEADER_HULL_MATERIAL, $headers, true);
        $boatTypeCol = array_search(AssetModelsGoogleSheetColumnRegistry::HEADER_BOAT_TYPE, $headers, true);

        $endRow = max($rowCount, 500);

        if ($makeCol !== false) {
            $this->sheets->applyListValidationFromReference(
                $integration,
                $spreadsheetId,
                $sheetId,
                1,
                $endRow,
                (int) $makeCol,
                "'{$referenceSheet}'!A2:A",
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
                "'{$referenceSheet}'!B2:B",
            );
        }

        if ($hullTypeCol !== false) {
            $this->sheets->applyListValidationFromReference(
                $integration,
                $spreadsheetId,
                $sheetId,
                1,
                $endRow,
                (int) $hullTypeCol,
                "'{$referenceSheet}'!C2:C",
            );
        }

        if ($hullMaterialCol !== false) {
            $this->sheets->applyListValidationFromReference(
                $integration,
                $spreadsheetId,
                $sheetId,
                1,
                $endRow,
                (int) $hullMaterialCol,
                "'{$referenceSheet}'!D2:D",
            );
        }

        if ($boatTypeCol !== false) {
            $this->sheets->applyListValidationFromReference(
                $integration,
                $spreadsheetId,
                $sheetId,
                1,
                $endRow,
                (int) $boatTypeCol,
                "'{$referenceSheet}'!E2:E",
            );
        }
    }
}
