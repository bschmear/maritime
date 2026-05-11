<?php

namespace App\Domain\Opportunity\Actions;

use App\Domain\Opportunity\Models\Opportunity as RecordModel;
use App\Domain\Opportunity\Services\OpportunityAddonsSync;
use App\Domain\Opportunity\Services\OpportunitySelectedOptionSync;
use App\Domain\Opportunity\Validation\OpportunityLineRequestRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateOpportunity
{
    public function __invoke(int $id, array $data): array
    {
        $rules = array_merge([
            'customer_id' => 'sometimes|integer|exists:customer_profiles,id',
            'user_id' => 'sometimes|integer|exists:users,id',
        ], OpportunityLineRequestRules::nested($data));

        $validated = Validator::make($data, $rules)->validate();

        try {
            $record = DB::transaction(function () use ($id, $data, $validated) {
                $record = RecordModel::findOrFail($id);
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
                $record->update($fillable);

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

                if (array_key_exists('assets', $data)) {
                    $syncData = [];
                    foreach ((array) $data['assets'] as $item) {
                        if (empty($item['asset_id'])) {
                            continue;
                        }
                        $syncData[(int) $item['asset_id']] = [
                            'quantity' => $item['quantity'] ?? 1,
                            'unit_price' => $item['unit_price'] ?? null,
                            'estimated_cost' => $item['estimated_cost'] ?? null,
                            'notes' => $item['notes'] ?? null,
                            'asset_variant_id' => ! empty($item['asset_variant_id']) ? (int) $item['asset_variant_id'] : null,
                            'asset_unit_id' => ! empty($item['asset_unit_id']) ? (int) $item['asset_unit_id'] : null,
                        ];
                    }
                    $record->assets()->sync($syncData);

                    app(OpportunitySelectedOptionSync::class)->sync($record, (array) $data['assets']);
                    app(OpportunityAddonsSync::class)->syncAssetAddons($record, (array) $data['assets']);
                }

                if (array_key_exists('inventory_items', $data)) {
                    app(OpportunityAddonsSync::class)->syncInventoryAddons($record, (array) $data['inventory_items']);
                }

                return $record->fresh();
            });

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateOpportunity', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
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
