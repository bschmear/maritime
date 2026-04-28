<?php

namespace App\Domain\Asset\Support;

use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\AssetSpecValue;
use Illuminate\Database\Eloquent\Model;

final class SyncAssetSpecValues
{
    /**
     * Upsert spec value rows for definitions that exist and apply to this asset type.
     * Stale or forged spec_ids from the client are ignored to avoid foreign key failures.
     *
     * @param  array<int, array{spec_id?: mixed, value_number?: mixed, value_text?: mixed, value_boolean?: mixed, unit?: mixed}>  $specs
     */
    public static function forSpecable(Model $specable, int $assetType, array $specs): void
    {
        $morph = $specable->getMorphClass();
        $specableKey = $specable->getKey();

        $requestedIds = collect($specs)
            ->pluck('spec_id')
            ->filter(fn ($v) => $v !== null && $v !== '' && $v !== 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        if ($requestedIds->isEmpty()) {
            return;
        }

        $validIds = AssetSpecDefinition::query()
            ->whereIn('id', $requestedIds)
            ->where(function ($q) use ($assetType) {
                $q->whereNull('asset_types')
                    ->orWhereJsonContains('asset_types', $assetType);
            })
            ->pluck('id')
            ->all();

        if ($validIds === []) {
            return;
        }

        $allowed = array_fill_keys($validIds, true);

        foreach ($specs as $spec) {
            if (empty($spec['spec_id'])) {
                continue;
            }
            $definitionId = (int) $spec['spec_id'];
            if (! isset($allowed[$definitionId])) {
                continue;
            }

            AssetSpecValue::updateOrCreate(
                [
                    'specable_type' => $morph,
                    'specable_id' => $specableKey,
                    'asset_spec_definition_id' => $definitionId,
                ],
                [
                    'value_number' => $spec['value_number'] ?? null,
                    'value_text' => $spec['value_text'] ?? null,
                    'value_boolean' => array_key_exists('value_boolean', $spec) ? $spec['value_boolean'] : null,
                    'unit' => $spec['unit'] ?? null,
                ]
            );
        }
    }
}
