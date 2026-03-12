<?php
namespace App\Domain\Estimate\Actions;

use App\Domain\Estimate\Models\Estimate as RecordModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Throwable;

class CreateEstimate
{
    public function __invoke(array $data): array
    {
        $validated = Validator::make($data, [
            'customer_id' => 'required|integer|exists:customers,id',
            'opportunity_id' => 'nullable|integer|exists:opportunities,id',
            'user_id' => 'required|integer|exists:users,id',
            'tax_rate' => 'nullable|numeric',
            'issue_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
        ])->validate();

        try {
            DB::beginTransaction();

            // 1. Create the Estimate
            $fillable = array_diff_key(
                $data,
                array_flip(['line_items', 'tenant_account'])
            );
            $record = RecordModel::create($fillable);

            // 2. Create the initial version (Version 1)
            $version = $record->versions()->create([
                'version' => 1,
                'status' => 'draft',
                'is_primary' => true,
                'tax_rate' => $data['tax_rate'] ?? null,
            ]);

            // 3. Create line items and add-ons
            $subtotal = 0;

            if (isset($data['line_items']) && is_array($data['line_items'])) {

                foreach ($data['line_items'] as $position => $lineData) {
                    $lineTotal = (float)($lineData['unit_price'] ?? 0) * (int)($lineData['quantity'] ?? 1) - (float)($lineData['discount'] ?? 0);

                    $lineItem = $version->lineItems()->create([
                        'itemable_type' => $lineData['itemable_type'] ?? null,
                        'itemable_id' => $lineData['itemable_id'] ?? null,
                        'name' => $lineData['name'] ?? '',
                        'description' => $lineData['description'] ?? null,
                        'quantity' => $lineData['quantity'] ?? 1,
                        'unit_price' => $lineData['unit_price'] ?? 0,
                        'discount' => $lineData['discount'] ?? 0,
                        'line_total' => $lineTotal,
                        'position' => $position,
                    ]);

                    $subtotal += $lineTotal;

                    if (isset($lineData['addons']) && is_array($lineData['addons'])) {
                        foreach ($lineData['addons'] as $addonData) {
                            $addonTotal = (float)($addonData['price'] ?? 0) * (int)($addonData['quantity'] ?? 1);

                            $lineItem->addons()->create([
                                'addon_id' => $addonData['addon_id'] ?? null,
                                'name' => $addonData['name'] ?? null,
                                'price' => $addonData['price'] ?? 0,
                                'quantity' => $addonData['quantity'] ?? 1,
                                'notes' => $addonData['notes'] ?? null,
                                'metadata' => $addonData['metadata'] ?? null,
                            ]);

                            $subtotal += $addonTotal;
                        }
                    }
                }
            }

            // 4. Calculate tax and total
            $taxRate = (float)($data['tax_rate'] ?? 0);
            $tax = $subtotal * ($taxRate / 100);
            $total = $subtotal + $tax;

            $version->update([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
            ]);

            // 5. Set primary version on estimate
            $record->update(['primary_version_id' => $version->id]);

            DB::commit();

            return [
                'success' => true,
                'record' => $record,
            ];
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Database query error in CreateEstimate', [
                'error' => $e->getMessage(),
                'data' => $data
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