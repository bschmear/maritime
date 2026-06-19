<?php

declare(strict_types=1);

namespace App\Domain\ChartOfAccount\Actions;

use App\Domain\ChartOfAccount\Models\ChartOfAccount as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateChartOfAccount
{
    /**
     * @return array{success: bool, record?: RecordModel, message?: string}
     */
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'name' => ['sometimes', 'string', 'max:255'],
            'quickbooks_account_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'account_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'detail_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fully_qualified_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:chart_of_accounts,id'],
        ])->validate();

        try {
            $record = RecordModel::query()->findOrFail($id);
            $record->update($validated);

            return ['success' => true, 'record' => $record->fresh()];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateChartOfAccount', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateChartOfAccount', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        }
    }
}
