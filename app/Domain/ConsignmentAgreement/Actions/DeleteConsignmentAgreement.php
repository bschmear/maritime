<?php

declare(strict_types=1);

namespace App\Domain\ConsignmentAgreement\Actions;

use App\Domain\ConsignmentAgreement\Models\ConsignmentAgreement as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteConsignmentAgreement
{
    /**
     * @return array{success: bool, message: string}
     */
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);
            if ($record->signed_at !== null) {
                return [
                    'success' => false,
                    'message' => 'Signed agreements cannot be deleted.',
                ];
            }
            $record->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteConsignmentAgreement', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteConsignmentAgreement', [
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
