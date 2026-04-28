<?php

declare(strict_types=1);

namespace App\Domain\WarrantyClaim\Actions;

use App\Domain\WarrantyClaim\Models\WarrantyClaim as RecordModel;
use App\Domain\WarrantyClaim\Models\WarrantyClaimLineItem;
use App\Enums\WarrantyClaim\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateWarrantyClaim
{
    public function __invoke(array $data): array
    {
        $validator = Validator::make($data, [
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'claim_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable'],
            'notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:2000'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.work_order_service_item_id' => ['nullable', 'integer', 'exists:work_order_service_items,id'],
        ]);
        $validated = $validator->validate();

        $items = $validated['items'] ?? [];
        unset($validated['items']);

        $status = Status::tryFromStored($validated['status'] ?? null) ?? Status::Draft;
        $validated['status'] = $status->value;
        $validated['total_amount'] = $this->sumItemsTotal($items);

        try {
            return DB::transaction(function () use ($validated, $items, $status) {
                $record = RecordModel::create($this->withLifecycleDefaults($validated, $status));

                foreach ($items as $row) {
                    WarrantyClaimLineItem::create([
                        'warranty_claim_id' => $record->id,
                        'work_order_service_item_id' => $row['work_order_service_item_id'] ?? null,
                        'description' => $row['description'],
                        'quantity' => (int) $row['quantity'],
                        'price' => round((float) $row['price'], 2),
                        'cost' => isset($row['cost']) ? round((float) $row['cost'], 2) : null,
                    ]);
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
     * @param  list<array<string, mixed>>  $items
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
