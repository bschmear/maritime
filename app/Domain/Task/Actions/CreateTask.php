<?php
namespace Domain\Task\Actions;

use Domain\Task\Models\Task as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class CreateTask
{
    public function __invoke(array $data): array
    {
  
            $validated = Validator::make($data, [
                'display_name' => ['required', 'string', 'max:255'],
                'notes'      => ['nullable', 'string'],
            ])->validate();

            $fieldsToSave = array_merge($data, $validated);

        try {

            $record = RecordModel::create($fieldsToSave);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateTask', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateTask', [
                'error' => $e->getMessage(),
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
