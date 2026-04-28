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
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateWarrantyClaim
{
    public function __invoke(int $id, array $data): array
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

        $items = array_key_exists('items', $data) ? ($validated['items'] ?? []) : null;
        unset($validated['items']);

        try {
            return DB::transaction(function () use ($id, $validated, $items) {
                $record = RecordModel::query()->lockForUpdate()->findOrFail($id);

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
                    $record->lineItems()->delete();
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
                    $validated['total_amount'] = $this->sumItemsTotal($items);
                }

                $record->update($validated);

                return [
                    'success' => true,
                    'record' => $record->fresh('lineItems'),
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
