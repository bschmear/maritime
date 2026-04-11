<?php

namespace App\Domain\Qualification\Actions;

use App\Domain\Qualification\Models\Qualification as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class CreateQualification
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'lead_id' => 'required|exists:lead_profiles,id',
            'user_id' => 'required',
            'status' => 'required',
        ])->validate();

        try {
            // Merge validated data with all input data to save everything
            $fieldsToSave = array_merge($data, $validated);

            // Set the createdby_id to the authenticated user
            $fieldsToSave['createdby_id'] = auth()->id();
            $fieldsToSave['uuid'] = (string) Str::uuid();

            $record = RecordModel::create($fieldsToSave);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateQualification', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateQualification', [
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
