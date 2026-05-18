<?php

namespace App\Domain\License\Actions;

use App\Domain\License\Models\License as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteLicense
{
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);
            $record->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteLicense', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteLicense', [
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
