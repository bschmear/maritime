<?php

namespace App\Domain\Opportunity\Actions;

use App\Domain\Opportunity\Models\Opportunity as RecordModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateOpportunity
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'customer_id' => 'required|integer|exists:customers,id',
            'user_id' => 'required|integer|exists:users,id',
            'stage' => 'required',
            'status' => 'required',
        ])->validate();

        try {
            $fillable = array_diff_key(
                array_merge($data, $validated),
                array_flip([
                    'inventory_items',
                    'assets',
                    'tenant_account',
                    'created_at',
                    'updated_at',
                    'uuid',
                    'sequence',
                ])
            );

            $fillable['createdby_id'] = auth()->id();

            $record = RecordModel::create($fillable);

            // Sync inventory items (Parts & Accessories)
            if (array_key_exists('inventory_items', $data)) {
                $syncData = [];
                foreach ((array) $data['inventory_items'] as $item) {
                    if (! empty($item['inventory_item_id'])) {
                        $syncData[$item['inventory_item_id']] = [
                            'quantity' => $item['quantity'] ?? 1,
                            'unit_price' => $item['unit_price'] ?? null,
                            'estimated_cost' => $item['estimated_cost'] ?? null,
                            'notes' => $item['notes'] ?? null,
                        ];
                    }
                }
                $record->inventoryItems()->sync($syncData);
            }

            // Sync assets (detach + attach so the same asset can appear with different variants)
            if (array_key_exists('assets', $data)) {
                $record->assets()->detach();
                foreach ((array) $data['assets'] as $item) {
                    if (empty($item['asset_id'])) {
                        continue;
                    }
                    $record->assets()->attach((int) $item['asset_id'], [
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => $item['unit_price'] ?? null,
                        'estimated_cost' => $item['estimated_cost'] ?? null,
                        'notes' => $item['notes'] ?? null,
                        'asset_variant_id' => ! empty($item['asset_variant_id']) ? (int) $item['asset_variant_id'] : null,
                    ]);
                }
            }

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateOpportunity', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateOpportunity', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}
