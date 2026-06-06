<?php

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Contact as RecordModel;
use App\Domain\Contact\Support\ContactDeletionGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteContact
{
    public function __construct(
        private readonly ContactDeletionGuard $deletionGuard = new ContactDeletionGuard,
    ) {}

    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::findOrFail($id);

            if ($message = $this->deletionGuard->messageFor($record)) {
                return [
                    'success' => false,
                    'message' => $message,
                ];
            }

            $record->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteContact', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteContact', [
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
