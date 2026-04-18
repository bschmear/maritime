<?php

namespace App\Enums\Integration;

enum IntegrationSyncStatus: int
{
    case Pending = 1;
    case Syncing = 2;
    case Success = 3;
    case Failed = 4;
    case Paused = 5;
    case Disconnected = 6;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Syncing => 'Syncing',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Paused => 'Paused',
            self::Disconnected => 'Disconnected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Syncing => 'blue',
            self::Success => 'green',
            self::Failed => 'red',
            self::Paused => 'orange',
            self::Disconnected => 'gray',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Waiting to sync',
            self::Syncing => 'Currently syncing data',
            self::Success => 'Last sync completed successfully',
            self::Failed => 'Last sync failed',
            self::Paused => 'Sync temporarily paused',
            self::Disconnected => 'Integration disconnected',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'id' => $case->value,
            'name' => $case->label(),
            'description' => $case->description(),
            'color' => $case->color(),
        ], self::cases());
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Failed, self::Paused, self::Disconnected]);
    }

    public function needsAttention(): bool
    {
        return in_array($this, [self::Failed, self::Disconnected]);
    }
}
