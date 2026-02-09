<?php
namespace App\Domain\InventoryUnit\Actions;

use App\Domain\InventoryUnit\Models\InventoryUnit as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateInventoryUnit
{
    public function __invoke(array $data): array
    {
        try {
            $validated = Validator::make($data, [
                'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
                'serial_number'     => ['nullable', 'string', 'max:255'],
                'sku'               => ['nullable', 'string', 'max:255'],
                'batch_number'      => ['nullable', 'string', 'max:255'],
                'quantity'          => ['nullable', 'integer', 'min:1'],
                'condition'         => ['nullable', 'integer'],
                'status'            => ['nullable', 'integer'],
                'engine_hours'      => ['nullable', 'integer', 'min:0'],
                'cost'              => ['nullable', 'numeric', 'min:0'],
                'asking_price'      => ['nullable', 'numeric', 'min:0'],
                'price_history'     => ['nullable', 'array'],
                'vendor_id'         => ['nullable', 'integer', 'exists:users,id'],
                'owner_name'        => ['nullable', 'string', 'max:255'],
                'location_id'       => ['nullable', 'integer', 'exists:locations,id'],
                'inactive'          => ['nullable', 'boolean'],
                'notes'             => ['nullable', 'string'],
            ])->validate();

            $fieldsToSave = $validated;

            // Remove fields that shouldn't be mass-assigned
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);

            // Create inventory unit
            $record = RecordModel::create($fieldsToSave);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in CreateInventoryUnit', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateInventoryUnit', [
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
