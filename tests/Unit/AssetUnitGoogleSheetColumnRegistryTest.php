<?php

declare(strict_types=1);

namespace Tests\Unit;

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
    public function base_headers_include_inventory_fields(): void
    {
        $registry = new AssetUnitGoogleSheetColumnRegistry;
        $headers = $registry->baseHeaders();

        $this->assertContains(AssetUnitGoogleSheetColumnRegistry::HEADER_UNIT_ID, $headers);
        $this->assertContains(AssetUnitGoogleSheetColumnRegistry::HEADER_STATUS, $headers);
        $this->assertContains(AssetUnitGoogleSheetColumnRegistry::HEADER_CONDITION, $headers);
        $this->assertContains(AssetUnitGoogleSheetColumnRegistry::HEADER_MAKE, $headers);
        $this->assertContains(AssetUnitGoogleSheetColumnRegistry::HEADER_VARIANT, $headers);
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
                'last_pushed_at' => '2026-06-21T12:00:00Z',
            ],
        ]);

        $settings = GoogleIntegrationSettings::from($integration);

        $this->assertSame('sheet-123', $settings->inventorySpreadsheetId());
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/sheet-123',
            $settings->spreadsheetUrl(),
        );
    }
}
