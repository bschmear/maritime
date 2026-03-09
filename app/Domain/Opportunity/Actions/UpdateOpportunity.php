<?php
namespace App\Domain\Opportunity\Actions;

use App\Domain\Opportunity\Models\Opportunity as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class UpdateOpportunity
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'customer_id' => 'sometimes|integer|exists:customers,id',
            'user_id'     => 'sometimes|integer|exists:users,id',
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);
            $fillable = array_diff_key(
                array_merge($data, $validated),
                array_flip(['inventory_items', 'assets', 'tenant_account'])
            );
            $record->update($fillable);

            // Sync inventory items (Parts & Accessories)
            if (array_key_exists('inventory_items', $data)) {
                $syncData = [];
                foreach ((array) $data['inventory_items'] as $item) {
                    if (!empty($item['inventory_item_id'])) {
                        $syncData[$item['inventory_item_id']] = [
                            'quantity'       => $item['quantity'] ?? 1,
                            'unit_price'     => $item['unit_price'] ?? null,
                            'estimated_cost' => $item['estimated_cost'] ?? null,
                            'notes'          => $item['notes'] ?? null,
                        ];
                    }
                }
                $record->inventoryItems()->sync($syncData);
            }

            // Sync assets
            if (array_key_exists('assets', $data)) {
                $syncData = [];
                foreach ((array) $data['assets'] as $item) {
                    if (!empty($item['asset_id'])) {
                        $syncData[$item['asset_id']] = [
                            'quantity'       => $item['quantity'] ?? 1,
                            'unit_price'     => $item['unit_price'] ?? null,
                            'estimated_cost' => $item['estimated_cost'] ?? null,
                            'notes'          => $item['notes'] ?? null,
                        ];
                    }
                }
                $record->assets()->sync($syncData);
            }

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateOpportunity', [
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
            Log::error('Unexpected error in UpdateOpportunity', [
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