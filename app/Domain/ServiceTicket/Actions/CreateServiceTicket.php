<?php
namespace App\Domain\ServiceTicket\Actions;

use App\Domain\ServiceTicket\Models\ServiceTicket as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Throwable;

class CreateServiceTicket
{
    public function __invoke(array $data): array
    {
        // Validate required fields
        Validator::make($data, [
            'customer_id' => 'required|exists:customers,id',
            'subsidiary_id' => 'required|exists:subsidiaries,id',
            'location_id' => 'required|exists:locations,id',
        ])->validate();

        // Generate UUID if not provided
        if (empty($data['uuid'])) {
            $data['uuid'] = (string) Str::uuid();
        }

        try {
            $record = RecordModel::create($data);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateServiceTicket', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateServiceTicket', [
                'error' => $e->getMessage(),
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