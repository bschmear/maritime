<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Actions;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Financing\Models\Financing;
use App\Domain\Financing\Support\FinancingCsvParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finds unlinked Financing records whose serial_vin normalizes to the same
 * value as the given AssetUnit's serial_number or hin, and links them.
 *
 * Called automatically after create/update of an AssetUnit so that importing
 * financing data before units exist still self-heals once the unit is added.
 */
class LinkFinancingsToAssetUnit
{
    public function __invoke(AssetUnit $unit): int
    {
        $candidates = collect([
            trim((string) ($unit->serial_number ?? '')),
            trim((string) ($unit->hin ?? '')),
        ])->filter()->unique()->values();

        if ($candidates->isEmpty()) {
            return 0;
        }

        $normalizedCandidates = $candidates->map(
            fn (string $v) => FinancingCsvParser::normalizeMatchValue($v)
        )->filter()->values();

        if ($normalizedCandidates->isEmpty()) {
            return 0;
        }

        // Build a SQL expression matching how FinancingCsvParser normalizes values
        $normSql = FinancingCsvParser::normalizedFieldSql('serial_vin');

        $matched = Financing::query()
            ->whereNull('asset_unit_id')
            ->whereNotNull('serial_vin')
            ->where(function ($q) use ($normSql, $normalizedCandidates): void {
                foreach ($normalizedCandidates as $val) {
                    $q->orWhereRaw("{$normSql} = ?", [$val]);
                }
            })
            ->get();

        if ($matched->isEmpty()) {
            return 0;
        }

        $linked = 0;

        foreach ($matched as $financing) {
            try {
                DB::transaction(function () use ($financing, $unit, &$linked): void {
                    $financing->update(['asset_unit_id' => $unit->id]);

                    // Mark the unit as financed if this financing is active
                    if (! $unit->is_financed && $financing->status?->value === 'active') {
                        $unit->update(['is_financed' => true]);
                        $unit->refresh();
                    }

                    $linked++;
                });
            } catch (\Throwable $e) {
                Log::warning('LinkFinancingsToAssetUnit: failed to link financing', [
                    'financing_id'  => $financing->id,
                    'asset_unit_id' => $unit->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        return $linked;
    }
}
