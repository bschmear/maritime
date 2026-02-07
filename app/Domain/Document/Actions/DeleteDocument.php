<?php
namespace App\Domain\Document\Actions;

use App\Domain\Document\Models\Document as RecordModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class DeleteDocument
{
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);

            // Note: File deletion is handled by the model's deleting event listener
            $record->delete();

            return [
                'success' => true,
                'message' => 'Document deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteDocument', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteDocument', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}