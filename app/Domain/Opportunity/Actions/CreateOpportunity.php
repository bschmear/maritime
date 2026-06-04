<?php

namespace App\Domain\Opportunity\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Opportunity\Models\Opportunity as RecordModel;
use App\Support\ContactPartyResolver;
use App\Domain\Opportunity\Services\OpportunityAddonsSync;
use App\Domain\Opportunity\Services\OpportunitySelectedOptionSync;
use App\Domain\Opportunity\Validation\OpportunityLineRequestRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateOpportunity
{
    public function __invoke(array $data): array
    {
        $rules = array_merge([
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'customer_id' => 'nullable|integer|exists:customer_profiles,id',
            'user_id' => 'required|integer|exists:users,id',
            'stage' => 'required',
            'status' => 'required',
        ], OpportunityLineRequestRules::nested($data));

        $validated = Validator::make($data, $rules)->validate();

        if (! empty($data['contact_id']) && empty($data['customer_id'])) {
            $contact = Contact::query()->findOrFail((int) $data['contact_id']);
            $data['customer_id'] = ContactPartyResolver::ensureCustomerProfile($contact)->id;
        }

        Validator::make($data, [
            'customer_id' => 'required|integer|exists:customer_profiles,id',
        ])->validate();

        $contactId = ! empty($data['contact_id'])
            ? (int) $data['contact_id']
            : (int) (Customer::query()->whereKey($data['customer_id'])->value('contact_id') ?? 0);

        if ($contactId > 0) {
            ContactPartyResolver::ensureLeadProfile($contactId);
        }

        try {
            $record = DB::transaction(function () use ($data, $validated) {
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
                        'contact_id',
                    ])
                );

                $fillable['createdby_id'] = current_tenant_user_id();

                $record = RecordModel::create($fillable);

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
