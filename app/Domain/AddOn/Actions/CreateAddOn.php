<?php
namespace App\Domain\AddOn\Actions;

use App\Domain\AddOn\Models\AddOn as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class CreateAddOn
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'default_price' => 'required|numeric',
            'type' => 'nullable|string|in:Asset,InventoryItem',
            'description' => 'nullable|string',
        ])->validate();

        try {
            $record = RecordModel::create($validated);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateAddOn', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateAddOn', [
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