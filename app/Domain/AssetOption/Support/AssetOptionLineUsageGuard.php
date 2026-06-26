<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Support;

use App\Domain\AssetOption\Models\EstimateSelectedOption;
use App\Domain\Customer\Models\CustomerAssetSpecSheetOptionSelection;
use App\Domain\Opportunity\Models\OpportunityAssetSelectedOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AssetOptionLineUsageGuard
{
    public const MESSAGE = 'This asset option cannot be deleted because it has been used on line items. Would you like to set it to inactive instead?';

    public function isUsedOnLineItems(int $optionId): bool
    {
        if (EstimateSelectedOption::query()->where('option_id', $optionId)->exists()) {
            return true;
        }

        if (Schema::hasTable('opportunity_asset_selected_options')
            && OpportunityAssetSelectedOption::query()->where('option_id', $optionId)->exists()) {
            return true;
        }

        if (Schema::hasTable('customer_asset_spec_sheet_option_selections')
            && CustomerAssetSpecSheetOptionSelection::query()->where('option_id', $optionId)->exists()) {
            return true;
        }

        if (Schema::hasColumn('transaction_line_items', 'customer_offered_option_ids')
            && $this->jsonArrayContainsOptionId('transaction_line_items', 'customer_offered_option_ids', $optionId)) {
            return true;
        }

        if (Schema::hasTable('asset_opportunity')
            && Schema::hasColumn('asset_opportunity', 'customer_offered_option_ids')
            && $this->jsonArrayContainsOptionId('asset_opportunity', 'customer_offered_option_ids', $optionId)) {
            return true;
        }

        return false;
    }

    private function jsonArrayContainsOptionId(string $table, string $column, int $optionId): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return DB::table($table)
                ->whereNotNull($column)
                ->whereRaw("{$column}::jsonb @> ?::jsonb", [json_encode([$optionId], JSON_THROW_ON_ERROR)])
                ->exists();
        }

        if ($driver === 'mysql') {
            return DB::table($table)
                ->whereNotNull($column)
                ->whereJsonContains($column, $optionId)
                ->exists();
        }

        return DB::table($table)
            ->whereNotNull($column)
            ->get([$column])
            ->contains(function ($row) use ($column, $optionId) {
                $raw = $row->{$column} ?? null;
                $ids = is_string($raw) ? json_decode($raw, true) : $raw;

                return is_array($ids) && in_array($optionId, array_map('intval', $ids), true);
            });
    }
}
