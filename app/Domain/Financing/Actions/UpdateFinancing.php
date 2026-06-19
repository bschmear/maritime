<?php

declare(strict_types=1);

namespace App\Domain\Financing\Actions;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\Financing\Models\Financing as RecordModel;
use App\Enums\Financing\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateFinancing
{
    public function __invoke(int $id, array $data): array
    {
        $validated = Validator::make($data, [
            'asset_unit_id' => 'nullable|sometimes|integer|exists:asset_units,id',
            'vendor_id' => 'sometimes|integer|exists:vendors,id',
            'dealer_name' => 'nullable|string|max:255',
            'dealer_cin' => 'nullable|string|max:64',
            'status' => 'sometimes|string|in:active,paid_off',
            'principal_amount' => 'nullable|numeric|min:0',
            'current_balance' => 'nullable|numeric|min:0',
            'annual_interest_rate' => 'nullable|numeric|min:0|max:100',
            'loan_term_months' => 'nullable|integer|min:1|max:600',
            'financed_at' => 'nullable|date',
            'interest_start_date' => 'nullable|date',
            'next_payment_date' => 'nullable|date',
            'monthly_payment_amount' => 'nullable|numeric|min:0',
            'lender_status' => 'nullable|string|max:255',
            'aging_days' => 'nullable|integer|min:0',
            'curtailment_current_due' => 'nullable|numeric|min:0',
            'past_due_curtailment' => 'nullable|numeric|min:0',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_cin' => 'nullable|string|max:64',
            'lender_invoice_number' => 'nullable|string|max:255',
            'model_year' => 'nullable|string|max:32',
            'model_number' => 'nullable|string|max:255',
            'serial_vin' => 'nullable|string|max:255',
            'days_alert_threshold' => 'nullable|integer|min:1',
            'interest_alert_threshold' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ])->validate();

        try {
            $record = RecordModel::findOrFail($id);
            $record->update($validated);

            $assetUnitId = $validated['asset_unit_id'] ?? $record->asset_unit_id;

            if ($assetUnitId !== null && ($validated['status'] ?? null) === Status::PaidOff->value) {
                AssetUnit::query()
                    ->whereKey($assetUnitId)
                    ->update(['is_financed' => false]);
            } elseif ($assetUnitId !== null && ($validated['status'] ?? null) === Status::Active->value) {
                AssetUnit::query()
                    ->whereKey($assetUnitId)
                    ->update(['is_financed' => true]);
            } elseif (array_key_exists('asset_unit_id', $validated) && $validated['asset_unit_id'] !== null) {
                AssetUnit::query()
                    ->whereKey($validated['asset_unit_id'])
                    ->update(['is_financed' => $record->status === Status::Active]);
            }

            return [
                'success' => true,
                'record' => $record->fresh(),
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateFinancing', [
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
            Log::error('Unexpected error in UpdateFinancing', [
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
