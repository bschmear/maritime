<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Contact\Support\ContactDeletionGuard;
use App\Domain\Customer\Models\Customer as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteCustomer
{
    public function __construct(
        private readonly ContactDeletionGuard $deletionGuard = new ContactDeletionGuard,
    ) {}

    /**
     * Deletes the contact when it is not referenced by other records.
     *
     * @return array{success: bool, message?: string}
     */
    public function __invoke(int $id): array
    {
        try {
            $record = RecordModel::query()->with('contact')->findOrFail($id);

            $contact = $record->contact;
            if ($contact && ($message = $this->deletionGuard->messageFor($contact))) {
                return [
                    'success' => false,
                    'message' => $message,
                ];
            }

            $contact?->delete();

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
