<?php
namespace App\Domain\ServiceTicket\Actions;

use App\Domain\ServiceTicket\Models\ServiceTicket as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateServiceTicket
{
    public function __invoke(int $id, array $data): array
    {
        // Validate required fields
        Validator::make($data, [
            'customer_id' => 'sometimes|exists:customers,id',
            'subsidiary_id' => 'sometimes|exists:subsidiaries,id',
            'location_id' => 'sometimes|exists:locations,id',
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($data);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateServiceTicket', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateServiceTicket', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}