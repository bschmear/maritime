<?php

namespace App\Domain\Lead\Actions;

use App\Domain\BoatShow\Models\BoatShowLead;
use App\Domain\Lead\Models\Lead as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteLead
{
    /**
     * Deletes the contact (cascades lead_profiles, addresses, and qualifications tied to the profile).
     *
     * @return array{success: bool, message?: string}
     */
    public function __invoke(int $id): array
    {
        try {
            $lead = RecordModel::query()->with('contact')->findOrFail($id);

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
