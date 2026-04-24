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

class CreateFleetMaintenance
{
    /**
     * @return array{success: true, record: RecordModel}|array{success: false, message: string, record: null}
     */
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, FleetMaintenanceInputRules::create())->validate();
        $typeIds = Arr::pull($validated, 'type_ids', []);
        $typeIds = self::normalizeTypeIds(is_array($typeIds) ? $typeIds : []);

        try {
            $record = DB::transaction(function () use ($validated, $typeIds) {
                $record = RecordModel::create($validated);
                if ($typeIds !== []) {
                    $record->maintenanceTypes()->sync($typeIds);
                }

                FleetMileageFromMaintenance::syncFromLog($record);

                return $record->load(['maintenanceTypes:id,display_name,category,applies_to']);
            });

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateFleetMaintenance', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateFleetMaintenance', [
                'error' => $e->getMessage(),
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
