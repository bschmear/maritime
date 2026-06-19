<?php

declare(strict_types=1);

namespace App\Domain\ChartOfAccount\Actions;

use App\Domain\ChartOfAccount\Models\ChartOfAccount as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateChartOfAccount
{
    /**
     * @return array{success: bool, record?: RecordModel, message?: string}
     */
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'quickbooks_account_id' => ['nullable', 'string', 'max:64'],
            'account_type' => ['nullable', 'string', 'max:255'],
            'detail_type' => ['nullable', 'string', 'max:255'],
            'fully_qualified_name' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ])->validate();

        $validated['active'] = filter_var($validated['active'] ?? true, FILTER_VALIDATE_BOOLEAN);

        try {
            $record = RecordModel::query()->create($validated);

            return ['success' => true, 'record' => $record];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateChartOfAccount', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateChartOfAccount', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        }
    }
}
