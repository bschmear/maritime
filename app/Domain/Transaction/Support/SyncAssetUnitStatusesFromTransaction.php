<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Support;

use App\Domain\AssetUnit\Actions\UpdateAssetUnit;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Validation\ValidationException;

final class SyncAssetUnitStatusesFromTransaction
{
    /**
     * @param  list<array{asset_unit_id?: mixed, status?: mixed}>  $updates
     */
    public static function apply(Transaction $transaction, array $updates): void
    {
        if ($updates === []) {
            return;
        }

        $allowedUnitIds = $transaction->items()
            ->whereNotNull('asset_unit_id')
            ->pluck('asset_unit_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($allowedUnitIds === []) {
            return;
        }

        $seen = [];
        $updateAssetUnit = app(UpdateAssetUnit::class);

        foreach ($updates as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $unitId = (int) ($row['asset_unit_id'] ?? 0);
            $status = (int) ($row['status'] ?? 0);

            if ($unitId <= 0 || $status < 1 || $status > 7) {
                throw ValidationException::withMessages([
                    "asset_unit_statuses.{$index}.status" => 'Each asset unit must have a valid status.',
                ]);
            }

            if (! in_array($unitId, $allowedUnitIds, true)) {
                throw ValidationException::withMessages([
                    "asset_unit_statuses.{$index}.asset_unit_id" => 'Asset unit is not linked to this transaction.',
                ]);
            }

            if (isset($seen[$unitId])) {
                continue;
            }
            $seen[$unitId] = true;

            $result = $updateAssetUnit($unitId, ['status' => $status]);
            if (! ($result['success'] ?? false)) {
                throw ValidationException::withMessages([
                    "asset_unit_statuses.{$index}.status" => $result['message'] ?? 'Could not update asset unit status.',
                ]);
            }
        }
    }
}
