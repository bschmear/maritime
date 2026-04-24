<?php

declare(strict_types=1);

namespace App\Domain\MaintenanceType\Actions;

use App\Domain\MaintenanceType\Models\MaintenanceType as RecordModel;
use App\Domain\MaintenanceType\Validation\MaintenanceTypeInputRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateMaintenanceType
{
    /**
     * @return array{success: true, record: RecordModel}|array{success: false, message: string, record: null}
     */
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, MaintenanceTypeInputRules::update())->validate();

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateMaintenanceType', [
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
            Log::error('Unexpected error in UpdateMaintenanceType', [
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
}
