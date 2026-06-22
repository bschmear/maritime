<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\GoogleIntegrationSettings;
use App\Enums\Integration\IntegrationType;
use App\Models\AccountSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleIntegrationSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
        ]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('account_settings', function (Blueprint $table) {
            $table->id();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('integration_type');
            $table->string('external_id')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        AccountSettings::query()->create(['settings' => []]);
    }

    #[Test]
    public function it_reads_inventory_spreadsheet_id_from_account_settings_when_integration_is_empty(): void
    {
        $account = AccountSettings::query()->firstOrFail();
        $account->settings = [
            'google' => ['inventory_spreadsheet_id' => 'sheet-from-account'],
        ];
        $account->save();

        $integration = new Integration([
            'integration_type' => IntegrationType::Google,
            'settings' => [],
        ]);

        $settings = GoogleIntegrationSettings::from($integration);

        $this->assertSame('sheet-from-account', $settings->inventorySpreadsheetId());
    }

    #[Test]
    public function it_persists_inventory_spreadsheet_id_to_integration_and_account_settings(): void
    {
        $integration = Integration::query()->create([
            'integration_type' => IntegrationType::Google,
            'external_id' => 'google-test',
            'settings' => [],
            'active' => true,
        ]);

        $settings = GoogleIntegrationSettings::from($integration);
        $settings->persistInventorySpreadsheet($integration, 'sheet-abc', 'Inventory');

        $integration->refresh();
        $account = AccountSettings::query()->firstOrFail();

        $this->assertSame('sheet-abc', $integration->settings['inventory_spreadsheet_id']);
        $this->assertSame('sheet-abc', $account->settings['google']['inventory_spreadsheet_id']);
    }

    #[Test]
    public function it_restores_workspace_spreadsheet_link_onto_integration(): void
    {
        $account = AccountSettings::query()->firstOrFail();
        $account->settings = [
            'google' => [
                'inventory_spreadsheet_id' => 'sheet-restored',
                'inventory_sheet_name' => 'Inventory',
            ],
        ];
        $account->save();

        $integration = Integration::query()->create([
            'integration_type' => IntegrationType::Google,
            'external_id' => 'google-restore',
            'settings' => [],
            'active' => true,
        ]);

        $settings = GoogleIntegrationSettings::from($integration);
        $settings->restoreWorkspaceSpreadsheetLink($integration);

        $integration->refresh();

        $this->assertSame('sheet-restored', $integration->settings['inventory_spreadsheet_id']);
    }

    #[Test]
    public function it_persists_models_spreadsheet_id_to_integration_and_account_settings(): void
    {
        $integration = Integration::query()->create([
            'integration_type' => IntegrationType::Google,
            'external_id' => 'google-models',
            'settings' => [],
            'active' => true,
        ]);

        $settings = GoogleIntegrationSettings::from($integration);
        $settings->persistModelsSpreadsheet($integration, 'models-abc', 'Models');

        $integration->refresh();
        $account = AccountSettings::query()->firstOrFail();

        $this->assertSame('models-abc', $integration->settings['models_spreadsheet_id']);
        $this->assertSame('models-abc', $account->settings['google']['models_spreadsheet_id']);
    }
}
