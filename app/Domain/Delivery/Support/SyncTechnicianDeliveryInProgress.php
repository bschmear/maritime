<?php

namespace App\Domain\Delivery\Support;

use App\Domain\Delivery\Models\Delivery;
use App\Domain\User\Models\User;

final class SyncTechnicianDeliveryInProgress
{
    /**
     * @param  array<int|string|null>  $userIds
     */
    public static function recomputeForUserIds(array $userIds): void
    {
        $ids = [];
        foreach ($userIds as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $ids[] = (int) $id;
        }
        $ids = array_values(array_unique($ids));
        foreach ($ids as $id) {
            self::recomputeForUserId($id);
        }
    }

    public static function recomputeForUserId(int $userId): void
    {
        $hasEnRoute = Delivery::query()
            ->where('technician_id', $userId)
            ->where('status', 'en_route')
            ->exists();

        User::whereKey($userId)->update(['delivery_in_progress' => $hasEnRoute]);
    }

    /**
     * After a delivery is saved, refresh the in-progress flag for the current and previous driver.
     */
    public static function syncForDelivery(Delivery $delivery, ?int $previousTechnicianId = null): void
    {
        self::recomputeForUserIds([
            $previousTechnicianId,
            $delivery->technician_id,
        ]);
    }
}
