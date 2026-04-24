<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Support;

use App\Domain\Delivery\Actions\SwapDeliveryFleetAssignments;
use App\Domain\Delivery\Exceptions\DeliveryFleetConflictException;
use App\Domain\Delivery\Models\Delivery;

final class DeliveryFleetConflictGuard
{
    /**
     * When fleet is assigned, ensure no overlapping use of truck/trailer windows; optionally swap with another delivery first.
     *
     * @throws DeliveryFleetConflictException
     */
    public static function assertResolved(Delivery $record, ?int $swapWithDeliveryId): Delivery
    {
        if ($record->fleet_truck_id === null && $record->fleet_trailer_id === null) {
            return $record;
        }

        $conflicts = DeliveryFleetOccupancy::findConflicts(
            $record->fleet_truck_id !== null ? (int) $record->fleet_truck_id : null,
            $record->fleet_trailer_id !== null ? (int) $record->fleet_trailer_id : null,
            $record,
            null
        );

        if ($conflicts === []) {
            return $record;
        }

        if ($swapWithDeliveryId !== null) {
            $swap = (new SwapDeliveryFleetAssignments)((int) $record->id, $swapWithDeliveryId);
            if (! ($swap['success'] ?? false)) {
                throw new DeliveryFleetConflictException(
                    (string) ($swap['message'] ?? 'Could not swap fleet assignments.'),
                    $conflicts
                );
            }
            /** @var array{0: Delivery, 1: Delivery} $records */
            $records = $swap['records'];
            $record = $records[0]->id === (int) $record->id ? $records[0] : $records[1];
            $record->refresh();

            $conflicts = DeliveryFleetOccupancy::findConflicts(
                $record->fleet_truck_id !== null ? (int) $record->fleet_truck_id : null,
                $record->fleet_trailer_id !== null ? (int) $record->fleet_trailer_id : null,
                $record,
                null
            );
        }

        if ($conflicts !== []) {
            throw new DeliveryFleetConflictException(
                'Fleet scheduling conflict: truck or trailer is already booked for this window.',
                $conflicts
            );
        }

        return $record;
    }
}
