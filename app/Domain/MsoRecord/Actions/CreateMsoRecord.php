<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Actions;

use App\Domain\MsoRecord\Models\MsoRecord as RecordModel;
use App\Enums\MsoRecord\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateMsoRecord
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'asset_unit_id' => ['required', 'integer', 'exists:asset_units,id'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'transaction_line_item_id' => ['nullable', 'integer', 'exists:transaction_line_items,id'],
            'source_document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'output_document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'details' => ['nullable', 'array'],
            'status' => ['nullable', 'string'],
            'created_by_id' => ['nullable', 'integer'],
            'submitted_at' => ['nullable', 'date'],
        ])->validate();

        try {
            $validated['created_by_id'] = $validated['created_by_id'] ?? current_tenant_user_id();
            $validated['status'] = $validated['status'] ?? Status::Draft->value;

            $record = RecordModel::query()->create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateMsoRecord', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateMsoRecord', [
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
