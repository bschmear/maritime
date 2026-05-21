<?php

declare(strict_types=1);

namespace App\Domain\Opportunity\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Models\OpportunityAssetSelectedOption;
use App\Domain\Opportunity\Models\OpportunityFeatureRequest;

/**
 * Persists customer boat-option picks from a feature request onto the opportunity asset line.
 */
class ApplyFeatureRequestAssetOptionSelections
{
    public function __construct(
        private OpportunitySelectedOptionSync $optionSync,
    ) {}

    /**
     * @param  array<int, array{option_id: int, option_value_id: int}>  $selections
     */
    public function applySubmission(
        Opportunity $opportunity,
        int $assetOpportunityId,
        Asset $asset,
        ?int $variantId,
        array $selections,
    ): void {
        if ($selections === []) {
            return;
        }

        $this->optionSync->sync($opportunity, [[
            'asset_id' => (int) $asset->id,
            'asset_variant_id' => $variantId,
            /** Must match the invite / pivot row — sync cannot infer this when the same asset appears on multiple lines. */
            'asset_opportunity_id' => $assetOpportunityId,
            'asset_option_selections' => $selections,
        ]]);
    }

    /**
     * Backfill pivot option rows from the latest feature request when missing (legacy submissions).
     */
    public function reconcileOpportunity(Opportunity $opportunity): void
    {
        $opportunity->loadMissing(['assets']);

        foreach ($opportunity->assets as $asset) {
            $pivotId = (int) ($asset->pivot->id ?? 0);
            if ($pivotId === 0) {
                continue;
            }

            if (OpportunityAssetSelectedOption::query()->where('asset_opportunity_id', $pivotId)->exists()) {
                continue;
            }

            $submission = OpportunityFeatureRequest::query()
                ->where('asset_opportunity_id', $pivotId)
                ->whereNotNull('submitted_at')
                ->orderByDesc('submitted_at')
                ->first();

            $selections = $submission?->asset_option_selections ?? [];
            if (! is_array($selections) || $selections === []) {
                continue;
            }

            $variantId = $asset->pivot->asset_variant_id ? (int) $asset->pivot->asset_variant_id : null;

            try {
                $this->applySubmission($opportunity, $pivotId, $asset, $variantId, $selections);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
