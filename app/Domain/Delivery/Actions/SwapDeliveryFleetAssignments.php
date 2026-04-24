<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Support\DeliveryFleetOccupancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SwapDeliveryFleetAssignments
{
    /**
     * @return array{success: true, records: array{0: RecordModel, 1: RecordModel}}|array{success: false, message: string}
     */
    public function __invoke(int $deliveryIdA, int $deliveryIdB): array
    {
        if ($deliveryIdA === $deliveryIdB) {
            return ['success' => false, 'message' => 'Cannot swap a delivery with itself.'];
        }

        try {
            return DB::transaction(function () use ($deliveryIdA, $deliveryIdB) {
                $a = RecordModel::query()->lockForUpdate()->findOrFail($deliveryIdA);
                $b = RecordModel::query()->lockForUpdate()->findOrFail($deliveryIdB);

                if ($b->status === 'en_route') {
                    return ['success' => false, 'message' => 'Cannot swap with a delivery that is en route.'];
                }
                if ($a->status === 'en_route') {
                    return ['success' => false, 'message' => 'Cannot swap fleet while this delivery is en route.'];
                }

                $tmpTruck = $a->fleet_truck_id;
                $tmpTrail = $a->fleet_trailer_id;
                $a->fleet_truck_id = $b->fleet_truck_id;
                $a->fleet_trailer_id = $b->fleet_trailer_id;
                $b->fleet_truck_id = $tmpTruck;
                $b->fleet_trailer_id = $tmpTrail;
                $a->save();
                $b->save();

                $a->refresh();
                $b->refresh();

                foreach ([$a, $b] as $d) {
                    $conf = DeliveryFleetOccupancy::findConflicts(
                        $d->fleet_truck_id !== null ? (int) $d->fleet_truck_id : null,
                        $d->fleet_trailer_id !== null ? (int) $d->fleet_trailer_id : null,
                        $d,
                        null
                    );
                    if ($conf !== []) {
                        throw new \RuntimeException('Swap would leave a fleet scheduling conflict.');
                    }
                }

                return [
                    'success' => true,
                    'records' => [$a->fresh(), $b->fresh()],
                ];
            });
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (QueryException $e) {
            Log::error('Database query error in SwapDeliveryFleetAssignments', [
                'error' => $e->getMessage(),
                'deliveryIdA' => $deliveryIdA,
                'deliveryIdB' => $deliveryIdB,
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Unexpected error in SwapDeliveryFleetAssignments', [
                'error' => $e->getMessage(),
                'deliveryIdA' => $deliveryIdA,
                'deliveryIdB' => $deliveryIdB,
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
