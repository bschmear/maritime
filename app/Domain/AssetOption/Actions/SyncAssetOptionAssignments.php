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
     * @param  array<int, array{asset_id: int, variant_id?: ?int}>  $rows
     */
    public function __invoke(int $optionId, int $makeId, bool $applyToAllModels, array $rows): void
    {
        $validator = Validator::make(
            [
                'option_id' => $optionId,
                'make_id' => $makeId,
                'rows' => $rows,
            ],
            [
                'option_id' => ['required', 'integer', 'exists:asset_options,id'],
                'make_id' => ['required', 'integer', 'exists:boat_make,id'],
                'rows' => ['array'],
                'rows.*.asset_id' => ['required', 'integer', 'exists:assets,id'],
                'rows.*.variant_id' => ['nullable', 'integer', 'exists:asset_variants,id'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        DB::transaction(function () use ($optionId, $makeId, $applyToAllModels, $rows): void {
            $assignmentQuery = AssetOptionAssignment::query()
                ->where('option_id', $optionId)
                ->whereHas('asset', fn ($q) => $q->where('make_id', $makeId));

            $makeQuery = AssetOptionMakeAssignment::query()
                ->where('option_id', $optionId)
                ->where('make_id', $makeId);

            if ($applyToAllModels) {
                $assignmentQuery->delete();
                AssetOptionMakeAssignment::query()->updateOrCreate(
                    [
                        'option_id' => $optionId,
                        'make_id' => $makeId,
                    ],
                    ['active' => true]
                );

                return;
            }

            $makeQuery->delete();

            $assignmentQuery->delete();

            foreach ($rows as $row) {
                $assetId = (int) $row['asset_id'];
                $variantId = isset($row['variant_id']) ? (int) $row['variant_id'] : null;

                $asset = Asset::query()->whereKey($assetId)->where('make_id', $makeId)->first();
                if ($asset === null) {
                    throw ValidationException::withMessages([
                        'rows' => 'Asset '.$assetId.' does not belong to the selected brand.',
                    ]);
                }

                if ($variantId !== null) {
                    $variant = AssetVariant::query()
                        ->whereKey($variantId)
                        ->where('asset_id', $assetId)
                        ->first();
                    if ($variant === null) {
                        throw ValidationException::withMessages([
                            'rows' => 'Variant '.$variantId.' does not belong to the selected asset.',
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
        });
    }
}
