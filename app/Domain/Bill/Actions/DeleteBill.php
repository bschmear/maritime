<?php

declare(strict_types=1);

namespace App\Domain\Bill\Actions;

use App\Domain\Bill\Models\Bill as RecordModel;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteBill
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function __invoke(int $id, bool $removeFromQuickbooks = false): array
    {
        try {
            $record = RecordModel::query()->findOrFail($id);

            if ($removeFromQuickbooks && $record->quickbooks_bill_id) {
                $result = app(QuickBooksAccountingService::class)->removeRemoteBill((string) $record->quickbooks_bill_id);
                if (! ($result['success'] ?? false)) {
                    return [
                        'success' => false,
                        'message' => $result['message'] ?? 'Could not remove bill from QuickBooks.',
                    ];
                }
            }

            $record->delete();

            return ['success' => true];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteBill', ['error' => $e->getMessage(), 'id' => $id]);

            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteBill', ['error' => $e->getMessage(), 'id' => $id]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
