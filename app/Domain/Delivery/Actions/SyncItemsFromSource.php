<?php

namespace App\Domain\Delivery\Actions;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Delivery\Models\DeliveryItem;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Models\TransactionItem;
use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Support\Collection;

class SyncItemsFromSource
{
    /**
     * Copy assets from the delivery's source (work_order or transaction) into delivery_items.
     *
     * Upserts by (itemable_type, itemable_id). Removes stale rows that used to originate
     * from the same source but no longer exist there. Preserves any user-added rows
     * (rows without an itemable link) and any already-delivered items.
     */
    public function __invoke(Delivery $delivery, string $sourceType, int $sourceId): Delivery
    {
        $source = $this->resolveSource($sourceType, $sourceId);
        if (! $source) {
            return $delivery;
        }

        $incoming = $this->buildItemsPayload($source);

        $keep = [];
        $position = 0;

        foreach ($incoming as $payload) {
            $position++;
            $attrs = array_merge($payload, [
                'delivery_id' => $delivery->id,
                'position' => $position,
            ]);

            $existing = DeliveryItem::where('delivery_id', $delivery->id)
                ->where('itemable_type', $payload['itemable_type'] ?? null)
                ->where('itemable_id', $payload['itemable_id'] ?? null)
                ->first();

            if ($existing) {
                // Don't clobber a user's manual price/qty edits too aggressively; refresh identity
                // + snapshot fields but preserve quantity/unit_price if the user changed them.
                $preserve = [];
                if ((float) $existing->quantity !== (float) ($payload['quantity'] ?? $existing->quantity)) {
                    $preserve['quantity'] = $existing->quantity;
                }
                if ((float) $existing->unit_price !== (float) ($payload['unit_price'] ?? $existing->unit_price)) {
                    $preserve['unit_price'] = $existing->unit_price;
                }

                $existing->fill(array_merge($attrs, $preserve))->save();
                $keep[] = $existing->id;
            } else {
                $created = DeliveryItem::create($attrs);
                $keep[] = $created->id;
            }
        }

        // Remove rows sourced from the same source that are no longer present.
        DeliveryItem::where('delivery_id', $delivery->id)
            ->whereNotNull('itemable_type')
            ->whereNotNull('itemable_id')
            ->whereNotIn('id', $keep)
            ->whereNull('delivered_at')
            ->delete();

        $delivery->load('items');
        $delivery->syncStatusFromItems();
        $delivery->save();

        return $delivery;
    }

    private function resolveSource(string $sourceType, int $sourceId)
    {
        $key = strtolower($sourceType);

        return match ($key) {
            'workorder', 'work_order' => WorkOrder::with(['assetUnit.asset', 'assetUnit.assetVariant'])
                ->find($sourceId),
            'transaction' => Transaction::with([
                'items.assetUnit.asset',
                'items.assetVariant',
            ])->find($sourceId),
            default => null,
        };
    }

    /**
     * Convert the raw source into an array of delivery_item row payloads (without delivery_id/position).
     */
    private function buildItemsPayload($source): Collection
    {
        if ($source instanceof WorkOrder) {
            // A WorkOrder owns a single asset_unit -- copy it as one delivery_item.
            $unit = $source->assetUnit;
            if (! $unit) {
                return collect();
            }

            return collect([$this->payloadFromUnit(
                $unit,
                name: $unit->display_name ?? ('Unit #'.$unit->id),
                description: null,
                quantity: 1,
                unitPrice: 0,
                assetVariantId: $unit->asset_variant_id ?? null,
                itemable: $source,
            )]);
        }

        if ($source instanceof Transaction) {
            $items = $source->items ?? collect();

            return $items->filter(function (TransactionItem $item) {
                // Only lines that map to a real asset unit count as "something to deliver".
                return ! empty($item->asset_unit_id);
            })->values()->map(function (TransactionItem $item) {
                $unit = $item->assetUnit;

                return $this->payloadFromUnit(
                    $unit,
                    name: $item->name ?? ($unit?->display_name ?? 'Asset'),
                    description: $item->description ?? null,
                    quantity: 1,
                    unitPrice: (float) ($item->unit_price ?? 0),
                    assetVariantId: $item->asset_variant_id ?? ($unit?->asset_variant_id ?? null),
                    itemable: $item,
                );
            });
        }

        return collect();
    }

    private function payloadFromUnit(
        ?AssetUnit $unit,
        ?string $name,
        ?string $description,
        float $quantity,
        float $unitPrice,
        $assetVariantId,
        $itemable,
    ): array {
        return [
            'type' => 'asset',
            'itemable_type' => $itemable ? get_class($itemable) : null,
            'itemable_id' => $itemable?->getKey(),
            'asset_unit_id' => $unit?->id,
            'asset_variant_id' => $assetVariantId,
            'name' => $name ?? 'Asset',
            'description' => $description,
            'quantity' => $quantity > 0 ? $quantity : 1,
            'unit_price' => $unitPrice,
            'serial_number_snapshot' => $unit?->serial_number
                ?? $unit?->hin
                ?? $unit?->sku
                ?? null,
        ];
    }
}
