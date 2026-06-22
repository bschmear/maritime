<?php

declare(strict_types=1);

namespace App\Domain\Integration\Support;

use App\Domain\Integration\Models\Integration;
use App\Models\AccountSettings;

final class GoogleIntegrationSettings
{
    public const INVENTORY_SHEET_NAME = 'Inventory';

    public const MODELS_SHEET_NAME = 'Models';

    public const REFERENCE_SHEET_NAME = '_Reference';

    private const ACCOUNT_SETTINGS_KEY = 'google';

    public function __construct(
        private readonly Integration $integration,
    ) {}

    public static function from(?Integration $integration): self
    {
        return new self($integration ?? new Integration);
    }

    public function driveFolderId(): ?string
    {
        return $this->stringSetting('drive_folder_id')
            ?? $this->accountGoogleString('drive_folder_id');
    }

    public function inventorySpreadsheetId(): ?string
    {
        return $this->stringSetting('inventory_spreadsheet_id')
            ?? $this->accountGoogleString('inventory_spreadsheet_id');
    }

    public function inventorySheetName(): string
    {
        return $this->stringSetting('inventory_sheet_name')
            ?? $this->accountGoogleString('inventory_sheet_name')
            ?? self::INVENTORY_SHEET_NAME;
    }

    public function modelsSpreadsheetId(): ?string
    {
        return $this->stringSetting('models_spreadsheet_id')
            ?? $this->accountGoogleString('models_spreadsheet_id');
    }

    public function modelsSheetName(): string
    {
        return $this->stringSetting('models_sheet_name')
            ?? $this->accountGoogleString('models_sheet_name')
            ?? self::MODELS_SHEET_NAME;
    }

    public function spreadsheetUrl(): ?string
    {
        $id = $this->inventorySpreadsheetId();

        return $id ? 'https://docs.google.com/spreadsheets/d/'.$id : null;
    }

    public function modelsSpreadsheetUrl(): ?string
    {
        $id = $this->modelsSpreadsheetId();

        return $id ? 'https://docs.google.com/spreadsheets/d/'.$id : null;
    }

    public function lastPushedAt(): ?string
    {
        return $this->stringSetting('last_pushed_at');
    }

    public function lastPulledAt(): ?string
    {
        return $this->stringSetting('last_pulled_at');
    }

    public function lastModelsPushedAt(): ?string
    {
        return $this->stringSetting('last_models_pushed_at');
    }

    public function lastModelsPulledAt(): ?string
    {
        return $this->stringSetting('last_models_pulled_at');
    }

    public function restoreWorkspaceSpreadsheetLink(Integration $integration): void
    {
        $spreadsheetId = $this->accountGoogleString('inventory_spreadsheet_id');
        if ($spreadsheetId === null || $this->stringSetting('inventory_spreadsheet_id') !== null) {
            return;
        }

        $this->persistInventorySpreadsheet($integration, $spreadsheetId, $this->inventorySheetName(), false);
    }

    public function restoreModelsSpreadsheetLink(Integration $integration): void
    {
        $spreadsheetId = $this->accountGoogleString('models_spreadsheet_id');
        if ($spreadsheetId === null || $this->stringSetting('models_spreadsheet_id') !== null) {
            return;
        }

        $this->persistModelsSpreadsheet($integration, $spreadsheetId, $this->modelsSheetName(), false);
    }

    public function persistInventorySpreadsheet(
        Integration $integration,
        string $spreadsheetId,
        ?string $sheetName = null,
        bool $touchSyncTimestamps = false,
    ): void {
        $values = [
            'inventory_spreadsheet_id' => $spreadsheetId,
        ];

        if ($sheetName !== null) {
            $values['inventory_sheet_name'] = $sheetName;
        }

        if ($touchSyncTimestamps) {
            $values['last_pushed_at'] = now()->toIso8601String();
        }

        $this->mergeIntoIntegration($integration, $values);
        $this->mergeIntoAccountSettings([
            'inventory_spreadsheet_id' => $spreadsheetId,
            'inventory_sheet_name' => $sheetName ?? $this->inventorySheetName(),
        ]);
    }

    public function persistModelsSpreadsheet(
        Integration $integration,
        string $spreadsheetId,
        ?string $sheetName = null,
        bool $touchSyncTimestamps = false,
    ): void {
        $values = [
            'models_spreadsheet_id' => $spreadsheetId,
        ];

        if ($sheetName !== null) {
            $values['models_sheet_name'] = $sheetName;
        }

        if ($touchSyncTimestamps) {
            $values['last_models_pushed_at'] = now()->toIso8601String();
        }

        $this->mergeIntoIntegration($integration, $values);
        $this->mergeIntoAccountSettings([
            'models_spreadsheet_id' => $spreadsheetId,
            'models_sheet_name' => $sheetName ?? $this->modelsSheetName(),
        ]);
    }

    public function clearInventorySpreadsheet(Integration $integration): void
    {
        $settings = is_array($integration->settings) ? $integration->settings : [];
        unset($settings['inventory_spreadsheet_id']);
        $integration->settings = $settings;
        $integration->save();

        $account = AccountSettings::getCurrent();
        $accountSettings = is_array($account->settings) ? $account->settings : [];
        $google = is_array($accountSettings[self::ACCOUNT_SETTINGS_KEY] ?? null)
            ? $accountSettings[self::ACCOUNT_SETTINGS_KEY]
            : [];
        unset($google['inventory_spreadsheet_id']);
        $accountSettings[self::ACCOUNT_SETTINGS_KEY] = $google;
        $account->settings = $accountSettings;
        $account->save();
    }

    public function clearModelsSpreadsheet(Integration $integration): void
    {
        $settings = is_array($integration->settings) ? $integration->settings : [];
        unset($settings['models_spreadsheet_id']);
        $integration->settings = $settings;
        $integration->save();

        $account = AccountSettings::getCurrent();
        $accountSettings = is_array($account->settings) ? $account->settings : [];
        $google = is_array($accountSettings[self::ACCOUNT_SETTINGS_KEY] ?? null)
            ? $accountSettings[self::ACCOUNT_SETTINGS_KEY]
            : [];
        unset($google['models_spreadsheet_id']);
        $accountSettings[self::ACCOUNT_SETTINGS_KEY] = $google;
        $account->settings = $accountSettings;
        $account->save();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function mergeIntoIntegration(Integration $integration, array $values): void
    {
        $settings = is_array($integration->settings) ? $integration->settings : [];
        $integration->settings = array_merge($settings, $values);
        $integration->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'drive_folder_id' => $this->driveFolderId(),
            'inventory_spreadsheet_id' => $this->inventorySpreadsheetId(),
            'inventory_sheet_name' => $this->inventorySheetName(),
            'spreadsheet_url' => $this->spreadsheetUrl(),
            'last_pushed_at' => $this->lastPushedAt(),
            'last_pulled_at' => $this->lastPulledAt(),
            'models_spreadsheet_id' => $this->modelsSpreadsheetId(),
            'models_sheet_name' => $this->modelsSheetName(),
            'models_spreadsheet_url' => $this->modelsSpreadsheetUrl(),
            'last_models_pushed_at' => $this->lastModelsPushedAt(),
            'last_models_pulled_at' => $this->lastModelsPulledAt(),
        ];
    }

    /**
     * @param  array<string, string|null>  $googleValues
     */
    private function mergeIntoAccountSettings(array $googleValues): void
    {
        $account = AccountSettings::getCurrent();
        $settings = is_array($account->settings) ? $account->settings : [];
        $google = is_array($settings[self::ACCOUNT_SETTINGS_KEY] ?? null)
            ? $settings[self::ACCOUNT_SETTINGS_KEY]
            : [];

        foreach ($googleValues as $key => $value) {
            if ($value === null || $value === '') {
                unset($google[$key]);
            } else {
                $google[$key] = $value;
            }
        }

        $settings[self::ACCOUNT_SETTINGS_KEY] = $google;
        $account->settings = $settings;
        $account->save();
    }

    private function stringSetting(string $key): ?string
    {
        $settings = $this->integration->settings ?? [];
        if (! is_array($settings)) {
            return null;
        }

        $value = $settings[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function accountGoogleString(string $key): ?string
    {
        $account = AccountSettings::getCurrent();
        $settings = $account->settings ?? [];
        if (! is_array($settings)) {
            return null;
        }

        $google = $settings[self::ACCOUNT_SETTINGS_KEY] ?? null;
        if (! is_array($google)) {
            return null;
        }

        $value = $google[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
