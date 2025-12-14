<?php
namespace App\Domain\InventoryItem\Actions;

use App\Domain\InventoryItem\Models\InventoryItem as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;

class CreateInventoryItem
{
    public function __invoke(array $data): array
    {
        try {
            $validated = Validator::make($data, [
                'type'          => ['required', 'integer'],
                'sku'           => ['nullable', 'string', 'max:50', 'unique:inventory_items,sku'],
                'display_name'  => ['required', 'string', 'max:255'],
                'make'          => ['nullable', 'string', 'max:255'],
                'model'         => ['nullable', 'string', 'max:255'],
                'year'          => ['nullable', 'string', 'max:10'],
                'length'        => ['nullable', 'string', 'max:50'],
                'engine_details'=> ['nullable', 'string', 'max:255'],
                'default_cost'  => ['nullable', 'numeric'],
                'default_price' => ['nullable', 'numeric'],
                'description'   => ['nullable', 'string'],
                'attributes'    => ['nullable', 'array'],
                'photos'        => ['nullable', 'array'],
                'videos'        => ['nullable', 'array']
            ])->validate();

            $fieldsToSave = array_merge($data, $validated);

            // // Generate slug if not provided
            if (!empty($fieldsToSave['name'])) {
                $fieldsToSave['slug'] = Str::slug($fieldsToSave['name']);
            }

            // Remove fields that shouldn't be mass-assigned
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);

            // Create inventory item
            $record = RecordModel::create($fieldsToSave);

            return [
                'success' => true,
                'record' => $record
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in CreateInventoryItem', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateInventoryItem', [
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
