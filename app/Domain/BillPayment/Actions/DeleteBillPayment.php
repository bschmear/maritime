<?php

declare(strict_types=1);

namespace App\Domain\BillPayment\Actions;

use App\Domain\BillPayment\Models\BillPayment as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteBillPayment
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
            Log::error('Database query error in DeleteBillPayment', ['error' => $e->getMessage(), 'id' => $id]);

            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteBillPayment', ['error' => $e->getMessage(), 'id' => $id]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
