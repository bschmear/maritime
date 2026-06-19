<?php

declare(strict_types=1);

namespace App\Domain\BillPayment\Actions;

use App\Domain\BillPayment\Models\BillPayment as RecordModel;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Jobs\PushBillPaymentToQuickBooks;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UpdateBillPayment
{
    /**
     * @return array{success: bool, record?: RecordModel, message?: string}
     */
    public function __invoke(int $id, array $data): array
    {
        $forImport = filter_var($data['for_import'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pushQuickbooks = filter_var($data['update_quickbooks'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $replaceLines = array_key_exists('lines', $data);
        $lines = $data['lines'] ?? [];
        unset($data['for_import'], $data['lines'], $data['update_quickbooks']);

        $validated = Validator::make($data, [
            'vendor_id' => ['sometimes', 'nullable', 'integer', 'exists:vendors,id'],
            'quickbooks_vendor_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'doc_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'txn_date' => ['sometimes', 'nullable', 'date'],
            'total_amt' => ['sometimes', 'numeric', 'min:0'],
            'pay_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'ap_account_ref_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'ap_account_ref_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_account_ref_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'bank_account_ref_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cc_account_ref_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'cc_account_ref_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'check_print_status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'exchange_rate' => ['sometimes', 'nullable', 'numeric'],
            'private_note' => ['sometimes', 'nullable', 'string'],
            'meta' => ['sometimes', 'nullable', 'array'],
            'quickbooks_bill_payment_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'quickbooks_sync_token' => ['sometimes', 'nullable', 'string', 'max:32'],
        ])->validate();

        if ($forImport && ($validated['vendor_id'] ?? null) === null) {
            unset($validated['vendor_id']);
        }

        try {
            $record = RecordModel::query()->findOrFail($id);

            $record = DB::transaction(function () use ($record, $validated, $replaceLines, $lines): RecordModel {
                $record->update($validated);

                if ($replaceLines) {
                    $record->lines()->delete();
                    foreach ($lines as $index => $line) {
                        if (! is_array($line)) {
                            continue;
                        }
                        $record->lines()->create([
                            'bill_id' => $line['bill_id'] ?? null,
                            'quickbooks_bill_id' => $line['quickbooks_bill_id'] ?? null,
                            'amount' => (float) ($line['amount'] ?? 0),
                            'position' => (int) ($line['position'] ?? $index),
                        ]);
                    }
                }

                return $record->fresh(['lines.bill', 'vendor']);
            });

            if (
                ! $forImport
                && ($pushQuickbooks || QuickBooksSettings::forCurrentTenant()->isSyncBillPaymentsEnabled())
                && $record->isQuickbooksManaged()
            ) {
                PushBillPaymentToQuickBooks::dispatch($record->id, update: true);
            }

            return ['success' => true, 'record' => $record];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateBillPayment', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateBillPayment', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        }
    }
}
