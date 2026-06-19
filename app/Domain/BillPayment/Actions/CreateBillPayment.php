<?php

declare(strict_types=1);

namespace App\Domain\BillPayment\Actions;

use App\Domain\BillPayment\Models\BillPayment as RecordModel;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Jobs\PushBillPaymentToQuickBooks;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateBillPayment
{
    /**
     * @return array{success: bool, record?: RecordModel, message?: string, quickbooks_sync?: array{success: bool, message?: string}}
     */
    public function __invoke(array $data): array
    {
        $forImport = filter_var($data['for_import'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $lines = $data['lines'] ?? [];
        unset($data['for_import'], $data['lines']);

        $validated = Validator::make($data, [
            'vendor_id' => [$forImport ? 'nullable' : 'required', 'integer', 'exists:vendors,id'],
            'quickbooks_vendor_id' => ['nullable', 'string', 'max:64'],
            'doc_number' => ['nullable', 'string', 'max:255'],
            'txn_date' => ['nullable', 'date'],
            'total_amt' => ['nullable', 'numeric', 'min:0'],
            'pay_type' => ['nullable', 'string', 'max:32'],
            'ap_account_ref_id' => ['nullable', 'string', 'max:64'],
            'ap_account_ref_name' => ['nullable', 'string', 'max:255'],
            'bank_account_ref_id' => ['nullable', 'string', 'max:64'],
            'bank_account_ref_name' => ['nullable', 'string', 'max:255'],
            'cc_account_ref_id' => ['nullable', 'string', 'max:64'],
            'cc_account_ref_name' => ['nullable', 'string', 'max:255'],
            'check_print_status' => ['nullable', 'string', 'max:32'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric'],
            'private_note' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
            'quickbooks_bill_payment_id' => ['nullable', 'string', 'max:64'],
            'quickbooks_sync_token' => ['nullable', 'string', 'max:32'],
        ])->validate();

        try {
            $record = DB::transaction(function () use ($validated, $lines): RecordModel {
                $record = RecordModel::query()->create($validated);
                $this->syncLines($record, is_array($lines) ? $lines : []);

                return $record->fresh(['lines.bill', 'vendor']);
            });

            $quickbooksSync = $this->pushToQuickBooksIfEnabled($record, $forImport);
            if ($quickbooksSync !== null) {
                $record = $record->fresh(['lines.bill', 'vendor']);
            }

            return [
                'success' => true,
                'record' => $record,
                'quickbooks_sync' => $quickbooksSync,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateBillPayment', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateBillPayment', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        }
    }

    /**
     * @return array{success: bool, message?: string}|null
     */
    private function pushToQuickBooksIfEnabled(RecordModel $record, bool $forImport): ?array
    {
        if ($forImport || ! QuickBooksSettings::forCurrentTenant()->isSyncBillPaymentsEnabled()) {
            return null;
        }

        if (! app(QuickBooksAccountingService::class)->isConnected()) {
            return null;
        }

        try {
            PushBillPaymentToQuickBooks::runSync($record->id);

            return [
                'success' => true,
                'message' => 'Synced to QuickBooks.',
            ];
        } catch (Throwable $e) {
            Log::error('QuickBooks sync after bill payment create failed', [
                'bill_payment_id' => $record->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncLines(RecordModel $payment, array $lines): void
    {
        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }
            $payment->lines()->create([
                'bill_id' => $line['bill_id'] ?? null,
                'quickbooks_bill_id' => $line['quickbooks_bill_id'] ?? null,
                'amount' => (float) ($line['amount'] ?? 0),
                'position' => (int) ($line['position'] ?? $index),
            ]);
        }
    }
}
