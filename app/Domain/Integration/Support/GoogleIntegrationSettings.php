<?php

declare(strict_types=1);

namespace App\Domain\Integration\Support;

use App\Domain\Integration\Models\Integration;

final class GoogleIntegrationSettings
{
    public const INVENTORY_SHEET_NAME = 'Inventory';

    public const REFERENCE_SHEET_NAME = '_Reference';

    public function __construct(
        private readonly Integration $integration,
    ) {}

    public static function from(?Integration $integration): self
    {
        return new self($integration ?? new Integration);
    }

    public function driveFolderId(): ?string
    {
        return $this->stringSetting('drive_folder_id');
    }

    public function inventorySpreadsheetId(): ?string
    {
        return $this->stringSetting('inventory_spreadsheet_id');
    }

    public function inventorySheetName(): string
    {
        return $this->stringSetting('inventory_sheet_name') ?? self::INVENTORY_SHEET_NAME;
    }

    public function spreadsheetUrl(): ?string
    {
        $id = $this->inventorySpreadsheetId();

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
        ];
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
}
