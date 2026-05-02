<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Actions;

use App\Domain\WarrantyClaim\Models\WarrantyClaim as RecordModel;
use App\Domain\WarrantyClaim\Models\WarrantyClaimLineItem;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrder\Support\SyncWorkOrderWarrantyFlags;
use App\Enums\WarrantyClaim\LineItemCostType;
use App\Enums\WarrantyClaim\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateWarrantyClaim
{
    public function __invoke(int $id, array $data): array
    {
        $record = RecordModel::query()->find($id);
        if (! $record) {
            return [
                'success' => false,
                'message' => 'Warranty claim not found.',
                'record' => null,
            ];
        }

        $lineItemPolicyStatus = $record->status instanceof Status
            ? $record->status
            : Status::tryFrom((string) $record->status) ?? Status::Draft;

        $rules = [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'subsidiary_id' => ['nullable', 'integer', 'exists:subsidiaries,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['nullable'],
            'notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
        ];

        if (array_key_exists('items', $data)) {
            if ($lineItemPolicyStatus !== Status::Draft) {
                $rules['items'] = ['nullable', 'array'];
                $rules['items.*.id'] = [
                    'required',
                    'integer',
                    Rule::exists('warranty_claim_line_items', 'id')->where('warranty_claim_id', $id),
                ];
                $rules['items.*.notes'] = ['nullable', 'string', 'max:10000'];
            } else {
                $rules['items'] = ['nullable', 'array'];
                $rules['items.*.description'] = ['required_with:items', 'string', 'max:2000'];
                $rules['items.*.cost_type'] = ['required_with:items', Rule::enum(LineItemCostType::class)];
                $rules['items.*.quantity'] = ['required_with:items', 'integer', 'min:1'];
                $rules['items.*.cost'] = ['required_with:items', 'numeric', 'min:0'];
                $rules['items.*.notes'] = ['nullable', 'string', 'max:10000'];
                $rules['items.*.work_order_service_item_id'] = ['nullable', 'integer', 'exists:work_order_service_items,id'];
            }
        }

        $validator = Validator::make($data, $rules);
        $validated = $validator->validate();

        if (! empty($validated['work_order_id'])) {
            $wo = WorkOrder::query()->find((int) $validated['work_order_id']);
            if ($wo) {
                $validated['subsidiary_id'] = $wo->subsidiary_id;
                $validated['location_id'] = $wo->location_id;
            }
        }

        $items = array_key_exists('items', $data) ? ($validated['items'] ?? null) : null;
        unset($validated['items']);

        try {
            return DB::transaction(function () use ($id, $validated, $items) {
                $record = RecordModel::query()->lockForUpdate()->findOrFail($id);

                $oldWorkOrderId = $record->work_order_id;

                $oldStatus = $record->status instanceof Status ? $record->status : Status::tryFrom((string) $record->status) ?? Status::Draft;
                $newStatus = isset($validated['status'])
                    ? (Status::tryFromStored($validated['status']) ?? $oldStatus)
                    : $oldStatus;
                $validated['status'] = $newStatus->value;

                $validated = array_merge(
                    $validated,
                    $this->lifecycleTimestamps($oldStatus, $newStatus, $validated)
                );

                if ($items !== null) {
                    if ($oldStatus !== Status::Draft) {
                        foreach ($items as $row) {
                            $lineId = (int) ($row['id'] ?? 0);
                            if ($lineId <= 0 || ! array_key_exists('notes', $row)) {
                                continue;
                            }
                            $notes = $row['notes'] !== '' && $row['notes'] !== null ? (string) $row['notes'] : null;
                            WarrantyClaimLineItem::query()
                                ->where('warranty_claim_id', $record->id)
                                ->whereKey($lineId)
                                ->update(['notes' => $notes]);
                        }
                    } else {
                        $record->lineItems()->delete();
                        foreach ($items as $row) {
                            $costType = LineItemCostType::tryFrom((string) ($row['cost_type'] ?? LineItemCostType::Quantity->value))
                                ?? LineItemCostType::Quantity;
                            $quantity = $costType === LineItemCostType::Fixed
                                ? 1
                                : max(1, (int) ($row['quantity'] ?? 1));

                            WarrantyClaimLineItem::create([
                                'warranty_claim_id' => $record->id,
                                'work_order_service_item_id' => $row['work_order_service_item_id'] ?? null,
                                'description' => $row['description'],
                                'cost_type' => $costType->value,
                                'quantity' => $quantity,
                                'cost' => round((float) ($row['cost'] ?? 0), 2),
                                'notes' => isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null,
                            ]);
                        }
                        $validated['total_amount'] = $this->sumItemsTotal($items);
                    }
                }

                $record->update($validated);

                $sync = app(SyncWorkOrderWarrantyFlags::class);
                $workOrderIds = array_unique(array_filter(
                    [$oldWorkOrderId, $record->work_order_id],
                    static fn ($v) => $v !== null && $v !== ''
                ));
                foreach ($workOrderIds as $woId) {
                    $wo = WorkOrder::query()->find((int) $woId);
                    if ($wo) {
                        ($sync)($wo);
                    }
                }

                return [
                    'success' => true,
                    'record' => $record->fresh(['lineItems.workOrderServiceItem']),
                ];
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateWarrantyClaim', [
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
            Log::error('Unexpected error in UpdateWarrantyClaim', [
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

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function sumItemsTotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $row) {
            $type = LineItemCostType::tryFrom((string) ($row['cost_type'] ?? LineItemCostType::Quantity->value))
                ?? LineItemCostType::Quantity;
            $qty = $type === LineItemCostType::Fixed ? 1 : max(1, (int) ($row['quantity'] ?? 1));
            $cost = (float) ($row['cost'] ?? 0);
            $total += $type->lineTotal($qty, $cost);
        }

        return round($total, 2);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function lifecycleTimestamps(Status $old, Status $new, array $validated): array
    {
        $out = [];
        $now = now();

        if ($new === Status::Submitted && $old !== Status::Submitted) {
            $out['submitted_at'] = $now;
        }
        if ($new === Status::Approved && $old !== Status::Approved) {
            $out['approved_at'] = $now;
        }
        if ($new === Status::Paid && $old !== Status::Paid) {
            $out['paid_at'] = $now;
        }
        if ($new === Status::Voided && $old !== Status::Voided) {
            $out['voided_at'] = $now;
        }

        return $out;
    }
}
