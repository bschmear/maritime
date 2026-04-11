<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\Customer as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteCustomer
{
    /**
     * Deletes the contact (cascades customer_profiles and addresses; related rows with ON DELETE CASCADE follow).
     *
     * @return array{success: bool, message?: string}
     */
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::query()->with('contact')->findOrFail($id);

            $record->contact?->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteCustomer', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteCustomer', [
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
