<?php

declare(strict_types=1);

namespace App\Domain\Bill\Actions;

use App\Domain\Bill\Models\Bill as RecordModel;
use App\Domain\Bill\Support\BillStatusResolver;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Enums\Bill\Status as BillStatus;
use App\Enums\Payments\Currency as PaymentsCurrency;
use App\Jobs\PushBillToQuickBooks;
use App\Services\Payments\QuickBooksAccountingService;
use App\Support\Enum\StoredEnumNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class CreateBill
{
    /**
     * @return array{success: bool, record?: RecordModel, message?: string, quickbooks_sync?: array{success: bool, message?: string}}
     */
    public function __invoke(array $data): array
    {
        $forImport = filter_var($data['for_import'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $items = $data['items'] ?? [];
        unset($data['for_import'], $data['items']);

        if (array_key_exists('status', $data)) {
            $data['status'] = StoredEnumNormalizer::normalizeForEnum($data['status'], BillStatus::class);
        }

        $validated = Validator::make($data, [
            'vendor_id' => [$forImport ? 'nullable' : 'required', 'integer', 'exists:vendors,id'],
            'quickbooks_vendor_id' => ['nullable', 'string', 'max:64'],
            'chart_of_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'doc_number' => ['nullable', 'string', 'max:255'],
            'txn_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'ap_account_ref_id' => ['nullable', 'string', 'max:64'],
            'ap_account_ref_name' => ['nullable', 'string', 'max:255'],
            'department_ref_id' => ['nullable', 'string', 'max:64'],
            'department_ref_name' => ['nullable', 'string', 'max:255'],
            'total_amt' => ['nullable', 'numeric', 'min:0'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                PaymentsCurrency::assertValidForValidation($value, $fail, $attribute);
            }],
            'exchange_rate' => ['nullable', 'numeric'],
            'private_note' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['open', 'overdue', 'paid', 'void'])],
            'meta' => ['nullable', 'array'],
            'quickbooks_bill_id' => ['nullable', 'string', 'max:64'],
            'quickbooks_sync_token' => ['nullable', 'string', 'max:32'],
        ])->validate();

        $validated['currency_code'] = PaymentsCurrency::toStoredValue($validated['currency_code'] ?? null) ?? 'USD';

        try {
            $record = DB::transaction(function () use ($validated, $items): RecordModel {
                if (! isset($validated['status'])) {
                    $balance = (float) ($validated['balance'] ?? $validated['total_amt'] ?? 0);
                    $dueDate = isset($validated['due_date']) ? Carbon::parse($validated['due_date']) : null;
                    $validated['status'] = BillStatusResolver::resolveValue($balance, $dueDate);
                }

                if (! isset($validated['balance']) && isset($validated['total_amt'])) {
                    $validated['balance'] = $validated['total_amt'];
                }

                $record = RecordModel::query()->create($validated);
                $this->syncItems($record, is_array($items) ? $items : []);

                return $record->fresh(['items', 'vendor', 'chartOfAccount']);
            });

            $quickbooksSync = $this->pushToQuickBooksIfEnabled($record, $forImport);
            if ($quickbooksSync !== null) {
                $record = $record->fresh(['items', 'vendor', 'chartOfAccount']);
            }

            return [
                'success' => true,
                'record' => $record,
                'quickbooks_sync' => $quickbooksSync,
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in CreateBill', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        } catch (Throwable $e) {
            Log::error('Unexpected error in CreateBill', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        }
    }

    /**
     * @return array{success: bool, message?: string}|null
     */
    private function pushToQuickBooksIfEnabled(RecordModel $record, bool $forImport): ?array
    {
        if ($forImport || ! QuickBooksSettings::forCurrentTenant()->isSyncBillsEnabled()) {
            return null;
        }

        if (! app(QuickBooksAccountingService::class)->isConnected()) {
            return null;
        }

        try {
            PushBillToQuickBooks::runSync($record->id);

            return [
                'success' => true,
                'message' => 'Synced to QuickBooks.',
            ];
        } catch (Throwable $e) {
            Log::error('QuickBooks sync after bill create failed', [
                'bill_id' => $record->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(RecordModel $bill, array $items): void
    {
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $bill->items()->create([
                'quickbooks_line_id' => $item['quickbooks_line_id'] ?? null,
                'amount' => (float) ($item['amount'] ?? 0),
                'description' => $item['description'] ?? null,
                'detail_type' => $item['detail_type'] ?? null,
                'chart_of_account_id' => $item['chart_of_account_id'] ?? null,
                'expense_account_ref_id' => $item['expense_account_ref_id'] ?? null,
                'expense_account_ref_name' => $item['expense_account_ref_name'] ?? null,
                'item_ref_id' => $item['item_ref_id'] ?? null,
                'item_ref_name' => $item['item_ref_name'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'position' => (int) ($item['position'] ?? $index),
            ]);
        }
    }
}
