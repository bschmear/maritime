<?php
namespace App\Domain\InventoryItem\Actions;

use App\Domain\InventoryItem\Models\InventoryItem as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

class UpdateInventoryItem
{
    public function __invoke(int $id, array $data): array
    {
        try {
            // Validate fields
            $validated = Validator::make($data, [
                'type'           => ['required', 'integer'],
                'sku'            => ['nullable', 'string', 'max:50', "unique:inventory_items,sku,{$id}"],
                'display_name'   => ['required', 'string', 'max:255'],
                'slug'           => ['nullable', 'string', 'max:255', "unique:inventory_items,slug,{$id}"],
                'make'           => ['nullable', 'string', 'max:255'],
                'model'          => ['nullable', 'string', 'max:255'],
                'year'           => ['nullable', 'string', 'max:10'],
                'length'         => ['nullable', 'string', 'max:50'],
                'engine_details' => ['nullable', 'string', 'max:255'],
                'default_cost'   => ['nullable', 'numeric'],
                'default_price'  => ['nullable', 'numeric'],
                'description'    => ['nullable', 'string'],
                'attributes'     => ['nullable', 'array'],
                'photos'         => ['nullable', 'array'],
                'videos'         => ['nullable', 'array'],
            ])->validate();

            // Find the record
            $record = RecordModel::findOrFail($id);

            // Merge validated fields with raw data (raw data takes precedence for fields without validation)
            $fieldsToUpdate = array_merge($data, $validated);

            // Update slug if name changed or slug provided
            if (!empty($fieldsToUpdate['name'])) {
                $fieldsToUpdate['slug'] = Str::slug($fieldsToUpdate['name']);
            }
            if (!empty($fieldsToUpdate['slug'])) {
                $fieldsToUpdate['slug'] = Str::slug($fieldsToUpdate['slug']);
            }

            // Remove non-mass-assignable fields
            unset($fieldsToUpdate['id'], $fieldsToUpdate['created_at'], $fieldsToUpdate['updated_at']);

            // Update the record
            $record->update($fieldsToUpdate);

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateInventoryItem', [
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
            Log::error('Unexpected error in UpdateInventoryItem', [
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
