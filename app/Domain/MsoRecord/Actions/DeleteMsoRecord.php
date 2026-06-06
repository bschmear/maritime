<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Actions;

use App\Domain\MsoRecord\Models\MsoRecord as RecordModel;
use App\Domain\MsoRecord\Support\SyncTransactionMsoFlags;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteMsoRecord
{
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::query()->findOrFail($id);
            $transactionId = $record->transaction_id;
            $record->delete();

            if ($transactionId) {
                SyncTransactionMsoFlags::forTransaction((int) $transactionId);
            }

            return [
                'success' => true,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteMsoRecord', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteMsoRecord', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
