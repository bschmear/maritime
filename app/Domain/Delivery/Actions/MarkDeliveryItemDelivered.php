<?php

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\DeliveryItem;

class MarkDeliveryItemDelivered
{
    /**
     * Toggle a single delivery item between delivered / not-delivered,
     * then let the parent delivery re-sync its aggregate status.
     *
     * $delivered=true stamps delivered_at+delivered_by_user_id.
     * $delivered=false clears them.
     */
    public function __invoke(DeliveryItem $item, ?int $userId, bool $delivered = true): DeliveryItem
    {
        if ($delivered) {
            $item->delivered_at = $item->delivered_at ?: now();
            $item->delivered_by_user_id = $userId;
        } else {
            $item->delivered_at = null;
            $item->delivered_by_user_id = null;
        }

        $item->save();

        $delivery = $item->delivery;
        if ($delivery) {
            $delivery->load('items');
            $delivery->syncStatusFromItems();
            $delivery->save();
        }

        return $item->fresh(['delivery', 'assetUnit', 'assetVariant', 'deliveredBy']);
    }
}
