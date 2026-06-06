<?php

namespace App\Domain\Lead\Actions;

use App\Domain\BoatShow\Models\BoatShowLead;
use App\Domain\Contact\Support\ContactDeletionGuard;
use App\Domain\Lead\Models\Lead as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteLead
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
            $lead = RecordModel::query()->with('contact')->findOrFail($id);

            $contact = $lead->contact;
            if ($contact && ($message = $this->deletionGuard->messageFor($contact))) {
                return [
                    'success' => false,
                    'message' => $message,
                ];
            }

            BoatShowLead::query()
                ->where('leadable_type', $lead->getMorphClass())
                ->where('leadable_id', $lead->getKey())
                ->delete();

            $lead->contact?->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteLead', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteLead', [
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
