<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Actions;

use App\Domain\WarrantyClaim\Models\WarrantyClaim as RecordModel;
use App\Domain\WarrantyClaim\Models\WarrantyClaimLineItem;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrder\Support\SyncWorkOrderWarrantyFlags;
use App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
use App\Enums\WarrantyClaim\LineItemCostType;
use App\Enums\WarrantyClaim\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateWarrantyClaim
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, ?int $createdByUserId = null): array
    {
        $woId = isset($data['work_order_id']) && $data['work_order_id'] !== '' && $data['work_order_id'] !== null
            ? (int) $data['work_order_id']
            : null;

        $manufacturerWarrantyLineCount = $woId
            ? (int) WorkOrderServiceItem::manufacturerWarrantyLinesForWorkOrder($woId)->count()
            : 0;

        $requiresManufacturerWarrantyLines = $woId !== null && $manufacturerWarrantyLineCount > 0;

        $rules = [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'subsidiary_id' => ['nullable', 'integer', 'exists:subsidiaries,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['nullable'],
            'notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
        ];

        if ($requiresManufacturerWarrantyLines) {
            $rules['items'] = ['required', 'array', 'min:1'];
            $rules['items.*.work_order_service_item_id'] = [
                'required',
                'integer',
                Rule::exists('work_order_service_items', 'id')->where(function ($q) use ($woId) {
                    $q->where('work_order_id', $woId)
                        ->where('warranty', true)
                        ->where('warranty_type', WarrantyCoverageType::Manufacturer->value)
                        ->where('inactive', false);
                }),
            ];
            $rules['items.*.cost_type'] = ['nullable', Rule::enum(LineItemCostType::class)];
            $rules['items.*.quantity'] = ['nullable', 'integer', 'min:1'];
            $rules['items.*.cost'] = ['nullable', 'numeric', 'min:0'];
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

        $validator = Validator::make($data, $rules);
        $validated = $validator->validate();

        if (! empty($validated['work_order_id'])) {
            $wo = WorkOrder::query()->find((int) $validated['work_order_id']);
            if ($wo) {
                $validated['subsidiary_id'] = $wo->subsidiary_id;
                $validated['location_id'] = $wo->location_id;
            }
        }

        $items = $validated['items'] ?? [];
        unset($validated['items']);

        $status = Status::tryFromStored($validated['status'] ?? null) ?? Status::Draft;
        $validated['status'] = $status->value;

        $normalizedLines = $this->normalizeLineItems($validated, $items);
        $validated['total_amount'] = $this->sumNormalizedLines($normalizedLines);

        try {
            return DB::transaction(function () use ($validated, $normalizedLines, $status, $createdByUserId) {
                $payload = $this->withLifecycleDefaults($validated, $status);
                if ($createdByUserId !== null && $createdByUserId > 0) {
                    $payload['created_by_user_id'] = $createdByUserId;
                }
                $record = RecordModel::create($payload);

                foreach ($normalizedLines as $row) {
                    WarrantyClaimLineItem::create([
                        'warranty_claim_id' => $record->id,
                        'work_order_service_item_id' => $row['work_order_service_item_id'],
                        'description' => $row['description'],
                        'cost_type' => $row['cost_type']->value,
                        'quantity' => $row['quantity'],
                        'cost' => $row['cost'],
                        'notes' => $row['notes'],
                    ]);
                }

                if (! empty($validated['work_order_id'])) {
                    $wo = WorkOrder::query()->find((int) $validated['work_order_id']);
                    if ($wo) {
                        (app(SyncWorkOrderWarrantyFlags::class))($wo);
                    }
                }

                return [
                    'success' => true,
                    'record' => $record->fresh(['lineItems.workOrderServiceItem']),
                ];
            });
        } catch (QueryException $e) {
            Log::error('Database query error in CreateWarrantyClaim', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateWarrantyClaim', [
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

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<array<string, mixed>>  $items
     * @return list<array{work_order_service_item_id: int|null, description: string, cost_type: LineItemCostType, quantity: int, cost: float, notes: string|null}>
     */
    private function normalizeLineItems(array $validated, array $items): array
    {
        $woId = ! empty($validated['work_order_id']) ? (int) $validated['work_order_id'] : 0;

        $out = [];
        foreach ($items as $row) {
            $wosiId = isset($row['work_order_service_item_id']) ? (int) $row['work_order_service_item_id'] : 0;

            if ($woId > 0 && $wosiId > 0) {
                $wosi = WorkOrderServiceItem::query()
                    ->whereKey($wosiId)
                    ->where('work_order_id', $woId)
                    ->first();
                if (! $wosi) {
                    throw ValidationException::withMessages([
                        'items' => ['One or more lines do not belong to this work order.'],
                    ]);
                }
                if (! $wosi->warranty || $wosi->warranty_type !== WarrantyCoverageType::Manufacturer || $wosi->inactive) {
                    throw ValidationException::withMessages([
                        'items' => ['One or more lines are not active manufacturer warranty items on this work order.'],
                    ]);
                }

                $inferredType = $this->inferCostTypeFromWorkOrderServiceItem($wosi);
                $defaults = $this->inferCostQuantityFromWorkOrderServiceItem($wosi, $inferredType);

                $costType = LineItemCostType::tryFrom((string) ($row['cost_type'] ?? '')) ?? $inferredType;

                $quantity = isset($row['quantity']) ? max(1, (int) $row['quantity']) : $defaults['quantity'];
                if ($costType === LineItemCostType::Fixed) {
                    $quantity = 1;
                }

                $cost = isset($row['cost']) ? round((float) $row['cost'], 2) : $defaults['cost'];

                $out[] = [
                    'work_order_service_item_id' => $wosi->id,
                    'description' => (string) $wosi->display_name,
                    'cost_type' => $costType,
                    'quantity' => $quantity,
                    'cost' => $cost,
                    'notes' => isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null,
                ];

                continue;
            }

            $costType = LineItemCostType::tryFrom((string) ($row['cost_type'] ?? LineItemCostType::Quantity->value))
                ?? LineItemCostType::Quantity;
            $quantity = $costType === LineItemCostType::Fixed
                ? 1
                : max(1, (int) ($row['quantity'] ?? 1));

            $out[] = [
                'work_order_service_item_id' => null,
                'description' => (string) ($row['description'] ?? ''),
                'cost_type' => $costType,
                'quantity' => $quantity,
                'cost' => round((float) ($row['cost'] ?? 0), 2),
                'notes' => isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null,
            ];
        }

        return $out;
    }

    private function inferCostTypeFromWorkOrderServiceItem(WorkOrderServiceItem $wosi): LineItemCostType
    {
        $billingType = (int) ($wosi->billing_type ?? 1);

        return $billingType === 2 ? LineItemCostType::Fixed : LineItemCostType::Quantity;
    }

    /**
     * @return array{quantity: int, cost: float}
     */
    private function inferCostQuantityFromWorkOrderServiceItem(WorkOrderServiceItem $wosi, LineItemCostType $type): array
    {
        if ($type === LineItemCostType::Fixed) {
            $total = $wosi->total_cost !== null
                ? (float) $wosi->total_cost
                : (float) ($wosi->unit_cost ?? 0) * max(1.0, (float) $wosi->quantity);

            return [
                'quantity' => 1,
                'cost' => round($total, 2),
            ];
        }

        $qty = (int) max(1, (int) round((float) $wosi->quantity));
        $unit = $wosi->unit_cost !== null
            ? (float) $wosi->unit_cost
            : ($wosi->total_cost !== null && $qty > 0 ? (float) $wosi->total_cost / $qty : 0.0);

        return [
            'quantity' => $qty,
            'cost' => round($unit, 2),
        ];
    }

    /**
     * @param  list<array{cost_type: LineItemCostType, quantity: int, cost: float}>  $rows
     */
    private function sumNormalizedLines(array $rows): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += $row['cost_type']->lineTotal($row['quantity'], $row['cost']);
        }

        return round($total, 2);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function withLifecycleDefaults(array $validated, Status $status): array
    {
        $now = now();
        if ($status === Status::Submitted) {
            $validated['submitted_at'] = $now;
        }
        if ($status === Status::Approved) {
            $validated['approved_at'] = $now;
        }
        if ($status === Status::Paid) {
            $validated['paid_at'] = $now;
        }
        if ($status === Status::Voided) {
            $validated['voided_at'] = $now;
        }

        return $validated;
    }
}
