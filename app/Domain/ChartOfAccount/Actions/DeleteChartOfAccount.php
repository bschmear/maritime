<?php

declare(strict_types=1);

namespace App\Domain\ChartOfAccount\Actions;

use App\Domain\ChartOfAccount\Models\ChartOfAccount as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteChartOfAccount
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::query()->findOrFail($id);
            $record->delete();

            return ['success' => true];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteChartOfAccount', ['error' => $e->getMessage(), 'id' => $id]);

            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteChartOfAccount', ['error' => $e->getMessage(), 'id' => $id]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
