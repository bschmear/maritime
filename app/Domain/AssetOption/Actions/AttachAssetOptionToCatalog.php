<?php

declare(strict_types=1);

namespace App\Domain\AssetOption\Actions;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Models\AssetOptionAssignment;
use App\Domain\AssetOption\Models\AssetOptionMakeAssignment;
use App\Domain\AssetVariant\Models\AssetVariant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttachAssetOptionToCatalog
{
    /**
     * Attach an option definition to the catalog for this asset/variant/brand (non-destructive; does not replace other assignments).
     *
     * @param  'variant'|'asset'|'brand'  $scope
     */
    public function __invoke(int $optionId, string $scope, Asset $asset, ?AssetVariant $variant = null): void
    {
        Validator::make(
            [
                'option_id' => $optionId,
                'scope' => $scope,
            ],
            [
                'option_id' => ['required', 'integer', 'exists:asset_options,id'],
                'scope' => ['required', 'string', Rule::in(['variant', 'asset', 'brand'])],
            ]
        )->validate();

        if ($scope === 'variant') {
            if ($variant === null) {
                throw ValidationException::withMessages([
                    'scope' => 'A variant is required for this scope.',
                ]);
            }
            if ((int) $variant->asset_id !== (int) $asset->id) {
                throw ValidationException::withMessages([
                    'variant_id' => 'Variant does not belong to this asset.',
                ]);
            }

            AssetOptionAssignment::query()->firstOrCreate(
                [
                    'option_id' => $optionId,
                    'asset_id' => $asset->id,
                    'variant_id' => $variant->id,
                ],
                ['active' => true]
            );

            return;
        }

        if ($scope === 'asset') {
            AssetOptionAssignment::query()->firstOrCreate(
                [
                    'option_id' => $optionId,
                    'asset_id' => $asset->id,
                    'variant_id' => null,
                ],
                ['active' => true]
            );

            return;
        }

        if ($asset->make_id === null) {
            throw ValidationException::withMessages([
                'asset_id' => 'This asset has no brand; cannot apply to all models of the brand.',
            ]);
        }

        AssetOptionMakeAssignment::query()->firstOrCreate(
            [
                'option_id' => $optionId,
                'make_id' => (int) $asset->make_id,
            ],
            ['active' => true]
        );
    }
}
