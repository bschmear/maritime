<?php
namespace App\Domain\ServiceTicketServiceItem\Actions;

use App\Domain\ServiceTicketServiceItem\Models\ServiceTicketServiceItem as RecordModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class DeleteServiceTicketServiceItem
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
            Log::error('Database query error in DeleteServiceTicketServiceItem', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DeleteServiceTicketServiceItem', [
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