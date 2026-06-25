<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Actions;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Models\AssetOptionAssignment;
use App\Domain\AssetOption\Models\AssetOptionMakeAssignment;
use App\Domain\AssetVariant\Models\AssetVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncAssetOptionAssignments
{
    /**
     * Replace all catalog assignments for an option (per-brand all-models or specific assets/variants).
     *
     * @param  array<int, array{make_id: int, apply_to_all_models: bool, rows?: array<int, array{asset_id: int, variant_id?: ?int}>}>  $brands
     */
    public function __invoke(int $optionId, array $brands): void
    {
        $validator = Validator::make(
            [
                'option_id' => $optionId,
                'brands' => $brands,
            ],
            [
                'option_id' => ['required', 'integer', 'exists:asset_options,id'],
                'brands' => ['array'],
                'brands.*.make_id' => ['required', 'integer', 'exists:boat_make,id'],
                'brands.*.apply_to_all_models' => ['required', 'boolean'],
                'brands.*.rows' => ['nullable', 'array'],
                'brands.*.rows.*.asset_id' => ['required', 'integer', 'exists:assets,id'],
                'brands.*.rows.*.variant_id' => ['nullable', 'integer', 'exists:asset_variants,id'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $makeIds = array_map(fn ($b) => (int) $b['make_id'], $brands);
        if (count($makeIds) !== count(array_unique($makeIds))) {
            throw ValidationException::withMessages([
                'brands' => 'Each brand may only appear once.',
            ]);
        }

        DB::transaction(function () use ($optionId, $brands): void {
            AssetOptionMakeAssignment::query()->where('option_id', $optionId)->delete();
            AssetOptionAssignment::query()->where('option_id', $optionId)->delete();

            foreach ($brands as $brand) {
                $makeId = (int) $brand['make_id'];
                $applyToAllModels = (bool) $brand['apply_to_all_models'];
                $rows = $brand['rows'] ?? [];

                if ($applyToAllModels) {
                    AssetOptionMakeAssignment::query()->create([
                        'option_id' => $optionId,
                        'make_id' => $makeId,
                        'active' => true,
                    ]);

                    continue;
                }

                foreach ($rows as $row) {
                    $assetId = (int) $row['asset_id'];
                    $variantId = isset($row['variant_id']) ? (int) $row['variant_id'] : null;

                    $asset = Asset::query()->whereKey($assetId)->where('make_id', $makeId)->first();
                    if ($asset === null) {
                        throw ValidationException::withMessages([
                            'brands' => 'Asset '.$assetId.' does not belong to one of the selected brands.',
                        ]);
                    }

                    if ($variantId !== null) {
                        $variant = AssetVariant::query()
                            ->whereKey($variantId)
                            ->where('asset_id', $assetId)
                            ->first();
                        if ($variant === null) {
                            throw ValidationException::withMessages([
                                'brands' => 'Variant '.$variantId.' does not belong to the selected asset.',
                            ]);
                        }
                    }

                    AssetOptionAssignment::query()->create([
                        'option_id' => $optionId,
                        'asset_id' => $assetId,
                        'variant_id' => $variantId,
                        'active' => true,
                    ]);
                }
            }
        });
    }
}
