<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Actions;

use App\Domain\WarrantyClaim\Models\WarrantyClaim as RecordModel;
use App\Domain\WarrantyClaim\Models\WarrantyClaimLineItem;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\WorkOrder\Support\SyncWorkOrderWarrantyFlags;
use App\Domain\WorkOrderServiceItem\Models\WorkOrderServiceItem;
use App\Enums\ServiceTicketServiceItem\WarrantyCoverageType;
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
    public function __invoke(array $data): array
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
        } else {
            $rules['items'] = ['nullable', 'array'];
            $rules['items.*.description'] = ['required_with:items', 'string', 'max:2000'];
            $rules['items.*.quantity'] = ['required_with:items', 'integer', 'min:1'];
            $rules['items.*.price'] = ['required_with:items', 'numeric', 'min:0'];
            $rules['items.*.cost'] = ['nullable', 'numeric', 'min:0'];
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
        $validated['total_amount'] = $this->sumItemsTotal($normalizedLines);

        try {
            return DB::transaction(function () use ($validated, $normalizedLines, $status) {
                $record = RecordModel::create($this->withLifecycleDefaults($validated, $status));

                foreach ($normalizedLines as $row) {
                    WarrantyClaimLineItem::create([
                        'warranty_claim_id' => $record->id,
                        'work_order_service_item_id' => $row['work_order_service_item_id'],
                        'description' => $row['description'],
                        'quantity' => $row['quantity'],
                        'price' => $row['price'],
                        'cost' => $row['cost'],
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
                    'record' => $record->fresh('lineItems'),
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
     * @return list<array{work_order_service_item_id: int|null, description: string, quantity: int, price: float, cost: float|null}>
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

                $qty = (int) max(1, (int) round((float) $wosi->quantity));
                $out[] = [
                    'work_order_service_item_id' => $wosi->id,
                    'description' => (string) $wosi->display_name,
                    'quantity' => $qty,
                    'price' => round((float) $wosi->unit_price, 2),
                    'cost' => $wosi->unit_cost !== null ? round((float) $wosi->unit_cost, 2) : null,
                ];

                continue;
            }

            $out[] = [
                'work_order_service_item_id' => null,
                'description' => (string) ($row['description'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 1),
                'price' => round((float) ($row['price'] ?? 0), 2),
                'cost' => isset($row['cost']) ? round((float) $row['cost'], 2) : null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{quantity: int, price: float}>  $items
     */
    private function sumItemsTotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $row) {
            $qty = (int) ($row['quantity'] ?? 1);
            $price = (float) ($row['price'] ?? 0);
            $total += $qty * $price;
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
