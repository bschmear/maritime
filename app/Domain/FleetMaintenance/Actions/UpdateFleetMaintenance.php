<?php

declare(strict_types=1);

namespace App\Domain\FleetMaintenance\Actions;

use App\Domain\FleetMaintenance\Models\FleetMaintenance as RecordModel;
use App\Domain\FleetMaintenance\Support\FleetMileageFromMaintenance;
use App\Domain\FleetMaintenance\Validation\FleetMaintenanceInputRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateFleetMaintenance
{
    /**
     * @return array{success: true, record: RecordModel}|array{success: false, message: string, record: null}
     */
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, FleetMaintenanceInputRules::update())->validate();
        $typeIds = Arr::pull($validated, 'type_ids', null);
        $typeIdsNormalized = is_array($typeIds) ? self::normalizeTypeIds($typeIds) : null;

        try {
            $record = DB::transaction(function () use ($id, $validated, $typeIdsNormalized) {
                $record = RecordModel::findOrFail($id);
                $record->update($validated);
                if ($typeIdsNormalized !== null) {
                    $record->maintenanceTypes()->sync($typeIdsNormalized);
                }

                FleetMileageFromMaintenance::syncFromLog($record);

                return $record->fresh(['maintenanceTypes:id,display_name,category,applies_to']);
            });

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateFleetMaintenance', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateFleetMaintenance', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private static function normalizeTypeIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[$n] = $n;
            }
        }

        return array_values($out);
    }
}
