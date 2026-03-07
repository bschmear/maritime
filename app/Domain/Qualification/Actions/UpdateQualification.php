<?php
namespace App\Domain\Qualification\Actions;

use App\Domain\Qualification\Models\Qualification as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateQualification
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'lead_id' => 'sometimes|required|exists:leads,id',
            'user_id' => 'sometimes|required|exists:users,id',
            'status'  => 'sometimes|required|in:open,contacted,qualified,converted,disqualified',
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);

            // Merge validated data with all input data to update everything
            $fieldsToUpdate = array_merge($data, $validated);

            $record->update($fieldsToUpdate);

            return [
                'success' => true,
                'record'  => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateQualification', [
                'error' => $e->getMessage(),
                'id'    => $id,
                'data'  => $data,
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record'  => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateQualification', [
                'error' => $e->getMessage(),
                'id'    => $id,
                'data'  => $data,
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record'  => null,
            ];
        }
    }
}