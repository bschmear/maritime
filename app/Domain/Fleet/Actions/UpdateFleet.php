<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Models\Fleet as RecordModel;
use App\Domain\Fleet\Validation\FleetInputRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateFleet
{
    /**
     * @return array{success: true, record: RecordModel}|array{success: false, message: string, record: null}
     */
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, FleetInputRules::update())->validate();

        try {
            $record = RecordModel::query()->findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record->refresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateFleet', [
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
            Log::error('Unexpected error in UpdateFleet', [
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
