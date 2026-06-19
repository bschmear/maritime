<?php

declare(strict_types=1);

namespace App\Domain\Bill\Actions;

use App\Domain\Bill\Models\Bill as RecordModel;
use App\Domain\Bill\Support\BillStatusResolver;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Domain\Vendor\Models\Vendor;
use App\Enums\Bill\Status as BillStatus;
use App\Enums\Payments\Currency as PaymentsCurrency;
use App\Jobs\PushBillToQuickBooks;
use App\Support\Enum\StoredEnumNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class UpdateBill
{
    /**
     * @return array{success: bool, record?: RecordModel, message?: string}
     */
    public function __invoke(int $id, array $data): array
    {
        $forImport = filter_var($data['for_import'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pushQuickbooks = filter_var($data['update_quickbooks'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $replaceItems = array_key_exists('items', $data);
        $items = $data['items'] ?? [];
        unset($data['for_import'], $data['items'], $data['update_quickbooks']);

        if (array_key_exists('status', $data)) {
            $data['status'] = StoredEnumNormalizer::normalizeForEnum($data['status'], BillStatus::class);
        }

        $validated = Validator::make($data, [
            'vendor_id' => ['sometimes', 'nullable', 'integer', 'exists:vendors,id'],
            'quickbooks_vendor_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'chart_of_account_id' => ['sometimes', 'nullable', 'integer', 'exists:chart_of_accounts,id'],
            'doc_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'txn_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'ap_account_ref_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'ap_account_ref_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department_ref_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'department_ref_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'total_amt' => ['sometimes', 'numeric', 'min:0'],
            'balance' => ['sometimes', 'numeric', 'min:0'],
            'currency_code' => ['sometimes', function (string $attribute, mixed $value, \Closure $fail): void {
                PaymentsCurrency::assertValidForValidation($value, $fail, $attribute);
            }],
            'exchange_rate' => ['sometimes', 'nullable', 'numeric'],
            'private_note' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(['open', 'overdue', 'paid', 'void'])],
            'meta' => ['sometimes', 'nullable', 'array'],
            'quickbooks_bill_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'quickbooks_sync_token' => ['sometimes', 'nullable', 'string', 'max:32'],
        ])->validate();

        if (array_key_exists('currency_code', $validated)) {
            $validated['currency_code'] = PaymentsCurrency::toStoredValue($validated['currency_code']) ?? 'USD';
        }

        if ($forImport && ($validated['vendor_id'] ?? null) === null) {
            unset($validated['vendor_id']);
        }

        try {
            $record = RecordModel::query()->findOrFail($id);
            $restrictedEdit = ! $forImport && $record->hasRestrictedEditing();
            $linkLineItemAccounts = $restrictedEdit && is_array($items) && $items !== [];

            if ($restrictedEdit) {
                $validated = array_intersect_key(
                    $validated,
                    array_flip(RecordModel::RESTRICTED_EDIT_ALLOWED_FIELDS),
                );
                $replaceItems = false;

                if (array_key_exists('vendor_id', $validated)) {
                    $vendorId = $validated['vendor_id'];
                    if ($vendorId) {
                        $qboVendorId = Vendor::query()->whereKey($vendorId)->value('quickbooks_id');
                        $validated['quickbooks_vendor_id'] = $qboVendorId ?: $record->quickbooks_vendor_id;
                    } else {
                        $validated['quickbooks_vendor_id'] = null;
                    }
                }
            }

            $record = DB::transaction(function () use ($record, $validated, $replaceItems, $items, $linkLineItemAccounts): RecordModel {
                if (
                    ! isset($validated['status'])
                    && (array_key_exists('balance', $validated) || array_key_exists('due_date', $validated))
                ) {
                    $balance = (float) ($validated['balance'] ?? $record->balance);
                    $dueDate = isset($validated['due_date'])
                        ? Carbon::parse($validated['due_date'])
                        : $record->due_date;
                    $validated['status'] = BillStatusResolver::resolveValue(
                        $balance,
                        $dueDate,
                        ($validated['status'] ?? $record->status) === 'void',
                    );
                }

                $record->update($validated);

                if ($replaceItems) {
                    $record->items()->delete();
                    foreach ($items as $index => $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $record->items()->create([
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
                } elseif ($linkLineItemAccounts) {
                    $this->syncRestrictedLineItemAccountLinks($record, $items);
                }

                return $record->fresh(['items.chartOfAccount', 'items', 'vendor', 'chartOfAccount']);
            });

            if (
                ! $forImport
                && ! $restrictedEdit
                && ($pushQuickbooks || QuickBooksSettings::forCurrentTenant()->isSyncBillsEnabled())
                && $record->isQuickbooksManaged()
            ) {
                PushBillToQuickBooks::dispatch($record->id, update: true);
            }

            return ['success' => true, 'record' => $record];
        } catch (QueryException $e) {
            Log::error('Database query error in UpdateBill', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        } catch (Throwable $e) {
            Log::error('Unexpected error in UpdateBill', ['error' => $e->getMessage(), 'data' => $data]);

            return ['success' => false, 'message' => $e->getMessage(), 'record' => null];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncRestrictedLineItemAccountLinks(RecordModel $bill, array $items): void
    {
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['id']) || ! array_key_exists('chart_of_account_id', $item)) {
                continue;
            }

            $lineId = (int) $item['id'];
            $accountId = $item['chart_of_account_id'];
            $accountId = $accountId === '' || $accountId === null ? null : (int) $accountId;

            $bill->items()
                ->whereKey($lineId)
                ->where('bill_id', $bill->id)
                ->update(['chart_of_account_id' => $accountId]);
        }
    }
}
