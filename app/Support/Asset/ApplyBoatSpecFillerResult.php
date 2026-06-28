<?php

declare(strict_types=1);

namespace App\Support\Asset;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Support\SyncAssetSpecValues;
use App\Domain\AssetVariant\Models\AssetVariant;

final class ApplyBoatSpecFillerResult
{
    /**
     * @param  list<array<string, mixed>>  $specUpdates
     * @param  list<array<string, mixed>>  $staticUpdates
     */
    public static function toAsset(Asset $asset, array $specUpdates, array $staticUpdates): void
    {
        if ($asset->has_variants) {
            throw new \InvalidArgumentException('Specs are stored per variant on this asset.');
        }

        $columns = [];
        foreach ($staticUpdates as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === 'length' || $key === 'width') {
                $columns[$key] = isset($row['value_number']) ? (int) round((float) $row['value_number']) : null;
            } elseif (in_array($key, ['hull_type', 'hull_material', 'boat_type'], true)) {
                $columns[$key] = isset($row['value_number']) ? (int) round((float) $row['value_number']) : null;
            }
        }

        if ($columns !== []) {
            $asset->fill($columns);
            $asset->save();
        }

        if ($specUpdates !== []) {
            SyncAssetSpecValues::forSpecable($asset, (int) $asset->type, $specUpdates);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $specUpdates
     * @param  list<array<string, mixed>>  $staticUpdates
     */
    public static function toVariant(Asset $asset, AssetVariant $variant, array $specUpdates, array $staticUpdates): void
    {
        $columns = [];
        foreach ($staticUpdates as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === 'length' || $key === 'width') {
                $columns[$key] = isset($row['value_number']) ? (int) round((float) $row['value_number']) : null;
            }
        }

        if ($columns !== []) {
            $variant->fill($columns);
            $variant->save();
        }

        if ($specUpdates !== []) {
            SyncAssetSpecValues::forSpecable($variant, (int) $asset->type, $specUpdates);
        }
    }
}
