<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

use App\Domain\Asset\Models\Asset;
use App\Domain\BoatMake\Models\BoatMake;

class InvoiceBrandCatalogBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function build(BoatMake $brand): array
    {
        return Asset::query()
            ->where('make_id', $brand->id)
            ->where(function ($q) {
                $q->where('inactive', false)->orWhereNull('inactive');
            })
            ->with(['variants' => function ($q) {
                $q->select('id', 'asset_id', 'display_name', 'name', 'key', 'description')
                    ->where(function ($inner) {
                        $inner->where('inactive', false)->orWhereNull('inactive');
                    })
                    ->orderBy('display_name');
            }])
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'model', 'year', 'description', 'has_variants'])
            ->map(function (Asset $asset) {
                return [
                    'asset_id' => $asset->id,
                    'display_name' => $asset->display_name,
                    'model' => $asset->model,
                    'year' => $asset->year,
                    'description' => $asset->description,
                    'has_variants' => (bool) $asset->has_variants,
                    'variants' => $asset->variants->map(fn ($v) => [
                        'asset_variant_id' => $v->id,
                        'display_name' => $v->display_name ?: $v->name,
                        'key' => $v->key,
                        'description' => $v->description,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
