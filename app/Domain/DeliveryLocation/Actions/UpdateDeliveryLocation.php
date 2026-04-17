<?php

namespace App\Domain\DeliveryLocation\Actions;

use App\Domain\DeliveryLocation\Models\DeliveryLocation as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateDeliveryLocation
{
    public function __invoke(int $id, array $data): array
    {
        $rules = CreateDeliveryLocation::rules();
        // Allow 'name' to be optional on update so callers can patch single fields.
        $rules['name'] = 'sometimes|required|string|max:255';

        $validated = Validator::make($data, $rules)->validate();

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateDeliveryLocation', [
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
            Log::error('Unexpected error in UpdateDeliveryLocation', [
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
