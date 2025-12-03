<?php
namespace Domain\Lead\Actions;

use Domain\Lead\Models\Lead as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateLead
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            // Add validation rules here
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateLead', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateLead', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}