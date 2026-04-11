<?php

declare(strict_types=1);

namespace App\Domain\ContactAddress\Actions;

use App\Domain\ContactAddress\Models\ContactAddress as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteContactAddress
{
    /**
     * @return array{success: bool, message?: string}
     */
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);
            $record->delete();

            return [
                'success' => true,
                'message' => 'Address deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteContactAddress', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteContactAddress', [
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
