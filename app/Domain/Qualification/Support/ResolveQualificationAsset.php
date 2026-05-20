<?php

namespace App\Domain\Qualification\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\Qualification\Models\Qualification;

class ResolveQualificationAsset
{
    /**
     * Find a catalog asset matching the qualification's desired brand + model name.
     */
    public function __invoke(Qualification $qualification): ?Asset
    {
        $makeId = $qualification->getRawOriginal('desired_brand');
        if (! $makeId) {
            return null;
        }

        $modelName = trim((string) ($qualification->desired_model ?? ''));
        if ($modelName === '') {
            return null;
        }

        $base = Asset::query()
            ->where('make_id', $makeId)
            ->with(['make:id,display_name']);

        $lower = strtolower($modelName);

        $asset = (clone $base)->where(function ($q) use ($lower) {
            $q->whereRaw('LOWER(display_name) = ?', [$lower])
                ->orWhereRaw('LOWER(COALESCE(model, \'\')) = ?', [$lower]);
        })->first();

        if ($asset) {
            return $asset;
        }

        return (clone $base)->where(function ($q) use ($modelName) {
            $q->where('display_name', 'like', '%'.$modelName.'%')
                ->orWhere('model', 'like', '%'.$modelName.'%');
        })->orderBy('display_name')->first();
    }
}
