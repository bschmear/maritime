<?php

declare(strict_types=1);

namespace App\Domain\MaintenanceType\Actions;

use App\Domain\MaintenanceType\Models\MaintenanceType as RecordModel;
use App\Domain\MaintenanceType\Validation\MaintenanceTypeInputRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateMaintenanceType
{
    /**
     * @return array{success: true, record: RecordModel}|array{success: false, message: string, record: null}
     */
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, MaintenanceTypeInputRules::create())->validate();

        try {
            if (! isset($validated['sort_order'])) {
                $validated['sort_order'] = (int) (RecordModel::query()->max('sort_order') ?? 0) + 1;
            }
            $record = RecordModel::create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateMaintenanceType', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateMaintenanceType', [
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
}
