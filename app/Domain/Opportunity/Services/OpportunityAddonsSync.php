<?php

declare(strict_types=1);

namespace App\Domain\Opportunity\Services;

use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Models\OpportunityAssetAddon;
use App\Domain\Opportunity\Models\OpportunityInventoryAddon;
use Illuminate\Support\Facades\DB;

class OpportunityAddonsSync
{
    /**
     * @param  array<int, array<string, mixed>>  $assetsPayload
     */
    public function syncAssetAddons(Opportunity $opportunity, array $assetsPayload): void
    {
        foreach ($assetsPayload as $item) {
            $assetId = (int) ($item['asset_id'] ?? 0);
            if ($assetId === 0) {
                continue;
            }

            $pivotId = $this->assetPivotId($opportunity->id, $assetId);
            if ($pivotId === null) {
                continue;
            }

            OpportunityAssetAddon::query()->where('asset_opportunity_id', $pivotId)->delete();

            foreach ($item['addons'] ?? [] as $addonData) {
                OpportunityAssetAddon::query()->create([
                    'asset_opportunity_id' => $pivotId,
                    'addon_id' => $addonData['addon_id'] ?? null,
                    'name' => $addonData['name'] ?? null,
                    'price' => $addonData['price'] ?? 0,
                    'quantity' => $addonData['quantity'] ?? 1,
                    'notes' => $addonData['notes'] ?? null,
                    'metadata' => $addonData['metadata'] ?? null,
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $inventoryPayload
     */
    public function syncInventoryAddons(Opportunity $opportunity, array $inventoryPayload): void
    {
        foreach ($inventoryPayload as $item) {
            $inventoryItemId = (int) ($item['inventory_item_id'] ?? 0);
            if ($inventoryItemId === 0) {
                continue;
            }

            $pivotId = $this->inventoryPivotId($opportunity->id, $inventoryItemId);
            if ($pivotId === null) {
                continue;
            }

            OpportunityInventoryAddon::query()->where('inventory_item_opportunity_id', $pivotId)->delete();

            foreach ($item['addons'] ?? [] as $addonData) {
                OpportunityInventoryAddon::query()->create([
                    'inventory_item_opportunity_id' => $pivotId,
                    'addon_id' => $addonData['addon_id'] ?? null,
                    'name' => $addonData['name'] ?? null,
                    'price' => $addonData['price'] ?? 0,
                    'quantity' => $addonData['quantity'] ?? 1,
                    'notes' => $addonData['notes'] ?? null,
                    'metadata' => $addonData['metadata'] ?? null,
                ]);
            }
        }
    }

    private function assetPivotId(int $opportunityId, int $assetId): ?int
    {
        $id = DB::table('asset_opportunity')
            ->where('opportunity_id', $opportunityId)
            ->where('asset_id', $assetId)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function inventoryPivotId(int $opportunityId, int $inventoryItemId): ?int
    {
        $id = DB::table('inventory_item_opportunity')
            ->where('opportunity_id', $opportunityId)
            ->where('inventory_item_id', $inventoryItemId)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
