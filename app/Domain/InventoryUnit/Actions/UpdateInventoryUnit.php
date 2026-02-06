<?php
namespace App\Domain\InventoryUnit\Actions;

use App\Domain\InventoryUnit\Models\InventoryUnit as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateInventoryUnit
{
    public function __invoke(int $id, array $data): array
    {
        try {
            // Validate fields (all optional for updates to support inline editing)
            $validated = Validator::make($data, [
                'inventory_item_id' => ['sometimes', 'integer', 'exists:inventory_items,id'],
                'serial_number'     => ['nullable', 'string', 'max:255'],
                'hin'               => ['nullable', 'string', 'max:255', 'unique:inventory_units,hin'],
                'sku'               => ['nullable', 'string', 'max:255'],
                'batch_number'      => ['nullable', 'string', 'max:255'],
                'quantity'          => ['nullable', 'integer', 'min:1'],
                'condition'         => ['nullable', 'integer'],
                'status'            => ['nullable', 'integer'],
                'engine_hours'      => ['nullable', 'integer', 'min:0'],
                'cost'              => ['nullable', 'numeric', 'min:0'],
                'asking_price'      => ['nullable', 'numeric', 'min:0'],
                'price_history'     => ['nullable', 'array'],
                'vendor_id'         => ['nullable', 'integer', 'exists:vendors,id'],
                'owner_name'        => ['nullable', 'string', 'max:255'],
                'location_id'       => ['nullable', 'integer', 'exists:locations,id'],
                'inactive'          => ['nullable', 'boolean'],
                'notes'             => ['nullable', 'string'],
            ])->validate();

            // Find the record
            $record = RecordModel::findOrFail($id);

            $fieldsToUpdate = $validated;

            // Remove non-mass-assignable fields
            unset($fieldsToUpdate['id'], $fieldsToUpdate['created_at'], $fieldsToUpdate['updated_at']);

            // Update the record
            $record->update($fieldsToUpdate);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateInventoryUnit', [
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
            Log::error('Unexpected error in UpdateInventoryUnit', [
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
