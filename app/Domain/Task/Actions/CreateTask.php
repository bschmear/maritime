<?php
namespace App\Domain\Task\Actions;

use App\Domain\Task\Models\Task as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class CreateTask
{
    public function __invoke(array $data): array
    {
        try {
            // Validate required fields
            $validator = Validator::make($data, [
                'display_name' => ['required', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
                'start_date' => ['nullable', 'date'],
                'due_date' => ['nullable', 'date'],
                'status_id' => ['nullable', 'integer'],
                'priority_id' => ['nullable', 'integer'],
                'assigned_id' => ['nullable', 'integer'],
                'completed' => ['nullable', 'boolean'],
                'task_type_id' => ['nullable', 'integer'],
                'relatable_type' => ['nullable', 'string'],
                'relatable_id' => ['nullable', 'integer'],
                'reminder_at' => ['nullable', 'date'],
                'snoozed_until' => ['nullable', 'date'],
                'recurring_rule' => ['nullable', 'string'],
            ]);

            $validated = $validator->validate();

            // Filter to only include fillable fields
            $fillableFields = (new RecordModel())->getFillable();
            $fieldsToSave = array_intersect_key($validated, array_flip($fillableFields));

            $record = RecordModel::create($fieldsToSave);
            
            // Load relationships for the response
            $record->load(['assigned', 'creator', 'updater']);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return [
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Validation failed',
                'record' => null,
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
