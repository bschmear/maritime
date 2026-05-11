<?php

declare(strict_types=1);

namespace App\Domain\Opportunity\Actions;

use App\Domain\AddOn\Models\AddOn;
use App\Domain\Opportunity\Models\OpportunityAssetAddon;

final class EnsureOpportunityAssetAddonFromCatalog
{
    /**
     * Create or update an {@see OpportunityAssetAddon} row from the tenant catalog for this asset line.
     */
    public function __invoke(int $assetOpportunityId, int $catalogAddonId, int $quantity): OpportunityAssetAddon
    {
        $catalog = AddOn::query()->findOrFail($catalogAddonId);

        $quantity = max(1, $quantity);

        $existing = OpportunityAssetAddon::query()
            ->where('asset_opportunity_id', $assetOpportunityId)
            ->where('addon_id', $catalogAddonId)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'name' => $catalog->name,
                'price' => $catalog->default_price,
                'quantity' => $quantity,
            ]);

            return $existing->fresh();
        }

        return OpportunityAssetAddon::query()->create([
            'asset_opportunity_id' => $assetOpportunityId,
            'addon_id' => $catalogAddonId,
            'name' => $catalog->name,
            'price' => $catalog->default_price,
            'quantity' => $quantity,
        ]);
    }
}
