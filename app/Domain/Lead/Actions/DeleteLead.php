<?php
namespace App\Domain\Lead\Actions;

use App\Domain\Lead\Models\Lead as RecordModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class DeleteLead
{
    /**
     * Handle the action.
     *
     * @param  int  $id
     * @return array
     */
    public function __invoke(int $id): array
    {
        try {
            $lead = RecordModel::findOrFail($id);
            $lead->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DeleteLead', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteLead', [
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