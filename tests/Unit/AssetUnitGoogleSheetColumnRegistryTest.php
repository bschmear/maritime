<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AssetUnit\Support\AssetModelsGoogleSheetColumnRegistry;
use App\Domain\AssetUnit\Support\AssetUnitGoogleSheetColumnRegistry;
use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\GoogleIntegrationSettings;
use App\Enums\Integration\IntegrationType;
use App\Enums\Inventory\UnitCondition;
use App\Enums\Inventory\UnitStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetUnitGoogleSheetColumnRegistryTest extends TestCase
{
    #[Test]
    public function inventory_base_headers_include_unit_fields_only(): void
    {
        $registry = new AssetUnitGoogleSheetColumnRegistry;
        $headers = $registry->baseHeaders();

        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_MAKE, $headers[0]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_ASSET_MODEL, $headers[1]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_VARIANT, $headers[2]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_STATUS, $headers[3]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_CONDITION, $headers[4]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_HIN, $headers[5]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_SERIAL, $headers[6]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_UNIT_YEAR, $headers[7]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_COST, $headers[8]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_ASKING_PRICE, $headers[9]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_LOCATION, $headers[10]);
        $this->assertSame(AssetUnitGoogleSheetColumnRegistry::HEADER_SUBSIDIARY, $headers[11]);
        $this->assertCount(12, $headers);
    }

    #[Test]
    public function models_base_headers_include_catalog_fields(): void
    {
        $registry = new AssetModelsGoogleSheetColumnRegistry;
        $headers = $registry->baseHeaders();

        $this->assertSame(AssetModelsGoogleSheetColumnRegistry::HEADER_MAKE, $headers[0]);
        $this->assertSame(AssetModelsGoogleSheetColumnRegistry::HEADER_MODEL, $headers[1]);
        $this->assertSame(AssetModelsGoogleSheetColumnRegistry::HEADER_VARIANT, $headers[2]);
        $this->assertSame(AssetModelsGoogleSheetColumnRegistry::HEADER_MODEL_YEAR, $headers[3]);
        $this->assertContains(AssetModelsGoogleSheetColumnRegistry::HEADER_HULL_TYPE, $headers);
        $this->assertContains(AssetModelsGoogleSheetColumnRegistry::HEADER_LENGTH, $headers);
        $this->assertContains(AssetModelsGoogleSheetColumnRegistry::HEADER_WIDTH, $headers);
    }

    #[Test]
    public function status_and_condition_labels_match_enums(): void
    {
        $registry = new AssetUnitGoogleSheetColumnRegistry;

        $this->assertSame(
            array_map(fn (array $o) => $o['name'], UnitStatus::options()),
            $registry->statusLabels(),
        );
        $this->assertSame(
            array_map(fn (array $o) => $o['name'], UnitCondition::options()),
            $registry->conditionLabels(),
        );
    }

    #[Test]
    public function google_integration_settings_reads_sheet_config(): void
    {
        $integration = new Integration([
            'integration_type' => IntegrationType::Google,
            'settings' => [
                'inventory_spreadsheet_id' => 'sheet-123',
                'models_spreadsheet_id' => 'sheet-models',
                'last_pushed_at' => '2026-06-21T12:00:00Z',
            ],
        ]);

        $settings = GoogleIntegrationSettings::from($integration);

        $this->assertSame('sheet-123', $settings->inventorySpreadsheetId());
        $this->assertSame('sheet-models', $settings->modelsSpreadsheetId());
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/sheet-123',
            $settings->spreadsheetUrl(),
        );
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/sheet-models',
            $settings->modelsSpreadsheetUrl(),
        );
    }
}
