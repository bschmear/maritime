<?php

namespace App\Domain\Estimate\Actions;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Services\EstimateSelectedOptionSync;
use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Estimate\Models\Estimate as RecordModel;
use App\Domain\Estimate\Support\LineItemDescription;
use App\Domain\Estimate\Support\RecalculateEstimateVersionTotals;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateEstimate
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'customer_id' => 'nullable|integer|exists:customer_profiles,id',
            'opportunity_id' => 'nullable|integer|exists:opportunities,id',
            'subsidiary_id' => 'nullable|integer|exists:subsidiaries,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'user_id' => 'required|integer|exists:users,id',
            'tax_rate' => 'nullable|numeric',
            'issue_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
        ])->validate();

        // Auto-resolve customer_id from contact when only contact_id is provided.
        if (! empty($data['contact_id']) && empty($data['customer_id'])) {
            $contact = Contact::findOrFail($data['contact_id']);
            $existing = Customer::where('contact_id', $contact->id)->first();
            if (! $existing) {
                $existing = Customer::create([
                    'contact_id' => $contact->id,
                    'account_status' => 'active',
                ]);
            }
            $data['customer_id'] = $existing->id;
        }

        Validator::make($data, [
            'customer_id' => 'required|integer|exists:customer_profiles,id',
        ])->validate();

        try {
            DB::beginTransaction();

            $record = RecordModel::findOrFail($id);

            $fillable = array_diff_key(
                $data,
                array_flip(['line_items', 'tenant_account', 'selected_asset_options'])
            );
            $record->update($fillable);

            $primaryVersion = $record->primaryVersion;

            if (! $primaryVersion) {
                $existingVersion = $record->versions()->where('is_primary', true)->first();

                if (! $existingVersion) {
                    $existingVersion = $record->versions()->orderBy('version', 'asc')->first();
                }

                if ($existingVersion) {
                    $existingVersion->update(['is_primary' => true]);
                    $record->update(['primary_version_id' => $existingVersion->id]);
                    $primaryVersion = $existingVersion;
                } else {
                    $primaryVersion = $record->versions()->create([
                        'version' => 1,
                        'status' => 'draft',
                        'is_primary' => true,
                        'tax_rate' => $data['tax_rate'] ?? null,
                    ]);
                    $record->update(['primary_version_id' => $primaryVersion->id]);
                }
            }

            if ($primaryVersion) {
                $primaryVersion->update(['tax_rate' => $data['tax_rate'] ?? null]);

                $primaryVersion->lineItems()->delete();

                $assetLineItemsByPosition = [];

                if (isset($data['line_items']) && is_array($data['line_items'])) {

                    foreach ($data['line_items'] as $position => $lineData) {
                        $lineTotal = (float) ($lineData['unit_price'] ?? 0) * (int) ($lineData['quantity'] ?? 1) - (float) ($lineData['discount'] ?? 0);

                        $lineItem = $primaryVersion->lineItems()->create([
                            'itemable_type' => $lineData['itemable_type'] ?? null,
                            'itemable_id' => $lineData['itemable_id'] ?? null,
                            'asset_variant_id' => ! empty($lineData['asset_variant_id']) ? (int) $lineData['asset_variant_id'] : null,
                            'asset_unit_id' => ! empty($lineData['asset_unit_id']) ? (int) $lineData['asset_unit_id'] : null,
                            'name' => $lineData['name'] ?? '',
                            'description' => LineItemDescription::merge($lineData),
                            'quantity' => $lineData['quantity'] ?? 1,
                            'unit_price' => $lineData['unit_price'] ?? 0,
                            'discount' => $lineData['discount'] ?? 0,
                            'line_total' => $lineTotal,
                            'position' => $position,
                            'asset_options_fill_mode' => (($lineData['asset_options_fill_mode'] ?? 'staff') === 'customer') ? 'customer' : 'staff',
                        ]);

                        if (($lineData['itemable_type'] ?? '') === Asset::class) {
                            $assetLineItemsByPosition[(int) $position] = $lineItem;
                        }

                        if (isset($lineData['addons']) && is_array($lineData['addons'])) {
                            foreach ($lineData['addons'] as $addonData) {
                                $lineItem->addons()->create([
                                    'addon_id' => $addonData['addon_id'] ?? null,
                                    'name' => $addonData['name'] ?? null,
                                    'price' => $addonData['price'] ?? 0,
                                    'quantity' => $addonData['quantity'] ?? 1,
                                    'notes' => $addonData['notes'] ?? null,
                                    'metadata' => $addonData['metadata'] ?? null,
                                ]);
                            }
                        }
                    }
                }

                app(EstimateSelectedOptionSync::class)->sync(
                    $record,
                    $data['line_items'] ?? [],
                    $assetLineItemsByPosition,
                    $data['selected_asset_options'] ?? [],
                );

                RecalculateEstimateVersionTotals::apply(
                    $primaryVersion->fresh(),
                    (float) ($data['tax_rate'] ?? 0)
                );
            }

            DB::commit();

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (ValidationException $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
                'record' => null,
            ];
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Database query error in UpdateEstimate', [
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
            DB::rollBack();
            Log::error('Unexpected error in UpdateEstimate', [
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
