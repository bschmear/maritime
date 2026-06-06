<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Actions;

use App\Domain\MsoRecord\Models\MsoRecord as RecordModel;
use App\Enums\MsoRecord\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateMsoRecord
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'asset_unit_id' => ['sometimes', 'integer', 'exists:asset_units,id'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'transaction_line_item_id' => ['nullable', 'integer', 'exists:transaction_line_items,id'],
            'source_document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'output_document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'details' => ['nullable', 'array'],
            'status' => ['nullable', 'string'],
            'submitted_at' => ['nullable', 'date'],
        ])->validate();

        try {
            $record = RecordModel::query()->findOrFail($id);

            if (isset($validated['status'])) {
                $status = Status::tryFrom($validated['status']);
                if ($status?->isResolved() && ! isset($validated['submitted_at'])) {
                    $validated['submitted_at'] = now();
                }
            }

            $record->fill($validated)->save();

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateMsoRecord', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateMsoRecord', [
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
