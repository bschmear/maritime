<?php

namespace App\Domain\Estimate\Actions;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Services\EstimateSelectedOptionSync;
use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Estimate\Models\Estimate as RecordModel;
use App\Domain\Estimate\Support\LineItemDescription;
use App\Domain\Estimate\Support\RecalculateEstimateVersionTotals;
use App\Support\ContactPartyResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateEstimate
{
    public function __invoke(array $data): array
    {
        // Allow either a contact_id (contact-first flow) or a customer_id (legacy).
        // At least one must be present; we resolve the other automatically.
        $validated = Validator::make($data, [
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'customer_id' => 'nullable|integer|exists:customer_profiles,id',
            'opportunity_id' => 'nullable|integer|exists:opportunities,id',
            'subsidiary_id' => 'nullable|integer|exists:subsidiaries,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'user_id' => 'required|integer|exists:users,id',
            'tax_rate' => 'nullable|numeric',
            'tax_jurisdiction' => 'nullable|string|max:255',
            'tax_jurisdiction_code' => 'nullable|string|max:32',
            'issue_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
        ])->validate();

        // If only a contact_id is supplied, auto-find or create the customer profile.
        if (! empty($data['contact_id']) && empty($data['customer_id'])) {
            $contact = Contact::findOrFail($data['contact_id']);
            $subsidiaryId = isset($data['subsidiary_id']) && $data['subsidiary_id'] !== '' && $data['subsidiary_id'] !== null
                ? (int) $data['subsidiary_id']
                : null;
            $data['customer_id'] = ContactPartyResolver::ensureCustomerProfile($contact, $subsidiaryId)->id;
        }

        // Validate customer_id is now resolved.
        Validator::make($data, [
            'customer_id' => 'required|integer|exists:customer_profiles,id',
        ])->validate();

        try {
            DB::beginTransaction();

            // 1. Create the Estimate
            $fillable = array_diff_key(
                $data,
                array_flip(['line_items', 'tenant_account', 'selected_asset_options'])
            );
            $record = RecordModel::create($fillable);

            // Ensure a lead profile exists for the contact (silent lifecycle step).
            if (! empty($data['contact_id'])) {
                ContactPartyResolver::ensureLeadProfile((int) $data['contact_id']);
            } elseif (! empty($data['customer_id'])) {
                $customer = Customer::find($data['customer_id']);
                if ($customer && $customer->contact_id) {
                    ContactPartyResolver::ensureLeadProfile((int) $customer->contact_id);
                }
            }

            // 2. Create the initial version (Version 1)
            $version = $record->versions()->create([
                'version' => 1,
                'status' => 'draft',
                'is_primary' => true,
                'tax_rate' => $data['tax_rate'] ?? null,
            ]);

            // 3. Create line items and add-ons
            $assetLineItemsByPosition = [];

            if (isset($data['line_items']) && is_array($data['line_items'])) {

                foreach ($data['line_items'] as $position => $lineData) {
                    $lineTotal = (float) ($lineData['unit_price'] ?? 0) * (int) ($lineData['quantity'] ?? 1) - (float) ($lineData['discount'] ?? 0);

                    $lineItem = $version->lineItems()->create([
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
                        'customer_offered_option_ids' => is_array($lineData['customer_offered_option_ids'] ?? null)
                            ? array_values(array_unique(array_map('intval', $lineData['customer_offered_option_ids'])))
                            : null,
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

            // 4. Roll option premiums into line totals and compute version subtotal / tax / total
            RecalculateEstimateVersionTotals::apply(
                $version->fresh(),
                (float) ($data['tax_rate'] ?? 0)
            );

            // 5. Set primary version on estimate
            $record->update(['primary_version_id' => $version->id]);

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
            Log::error('Database query error in CreateEstimate', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Unexpected error in CreateEstimate', [
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
