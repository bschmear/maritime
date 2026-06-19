<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\Bill\Support\BillStatusResolver;
use App\Domain\ChartOfAccount\Models\ChartOfAccount;
use App\Enums\Bill\Status as BillStatus;
use Illuminate\Support\Carbon;

final class QuickBooksBillMapper
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function mapBillRow(array $row): array
    {
        $qboId = QuickBooksRowMapper::refValue(['value' => $row['Id'] ?? null]);
        $vendorRef = is_array($row['VendorRef'] ?? null) ? $row['VendorRef'] : null;
        $vendorQboId = QuickBooksVendorResolver::quickbooksVendorIdFromRef($vendorRef);
        $vendorId = QuickBooksVendorResolver::resolveLocalVendorId($vendorRef);

        $balance = QuickBooksRowMapper::parseMoney($row['Balance'] ?? null);
        $total = QuickBooksRowMapper::parseMoney($row['TotalAmt'] ?? $balance);
        $dueDate = ! empty($row['DueDate']) ? (string) $row['DueDate'] : null;
        $dueCarbon = $dueDate ? Carbon::parse($dueDate) : null;

        $lines = self::mapLineRows($row['Line'] ?? []);
        $chartOfAccountId = self::resolveChartOfAccountIdFromLines($lines, $row);

        $meta = [
            'quickbooks_bill_url' => self::billUrl($qboId),
        ];

        return [
            'quickbooks_bill_id' => $qboId,
            'quickbooks_sync_token' => QuickBooksRowMapper::normalizeString($row['SyncToken'] ?? null) ?: null,
            'vendor_id' => $vendorId,
            'quickbooks_vendor_id' => $vendorQboId,
            'chart_of_account_id' => $chartOfAccountId,
            'doc_number' => QuickBooksRowMapper::normalizeString($row['DocNumber'] ?? null) ?: null,
            'txn_date' => ! empty($row['TxnDate']) ? (string) $row['TxnDate'] : null,
            'due_date' => $dueDate,
            'ap_account_ref_id' => QuickBooksRowMapper::refValue($row['APAccountRef'] ?? null) ?: null,
            'ap_account_ref_name' => QuickBooksRowMapper::refName($row['APAccountRef'] ?? null) ?: null,
            'department_ref_id' => QuickBooksRowMapper::refValue($row['DepartmentRef'] ?? null) ?: null,
            'department_ref_name' => QuickBooksRowMapper::refName($row['DepartmentRef'] ?? null) ?: null,
            'total_amt' => $total,
            'balance' => $balance,
            'currency_code' => QuickBooksRowMapper::refValue($row['CurrencyRef'] ?? null) ?: 'USD',
            'exchange_rate' => isset($row['ExchangeRate']) ? (float) $row['ExchangeRate'] : null,
            'private_note' => QuickBooksRowMapper::normalizeString($row['PrivateNote'] ?? null) ?: null,
            'status' => self::resolveStatusFromQuickBooks($row, $balance, $dueCarbon),
            'meta' => $meta,
            'items' => $lines,
            'for_import' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $row
     */
    private static function resolveChartOfAccountIdFromLines(array $items, array $row): ?int
    {
        foreach ($items as $item) {
            if (! empty($item['chart_of_account_id'])) {
                return (int) $item['chart_of_account_id'];
            }
        }

        foreach (QuickBooksRowMapper::normalizeList($row['Line'] ?? []) as $line) {
            if (! is_array($line)) {
                continue;
            }

            $chartOfAccountId = self::resolveChartOfAccountIdFromLineDetail($line);
            if ($chartOfAccountId !== null) {
                return $chartOfAccountId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function resolveChartOfAccountIdFromLineDetail(array $line): ?int
    {
        foreach (['AccountBasedExpenseLineDetail', 'ItemBasedExpenseLineDetail'] as $detailKey) {
            $detail = $line[$detailKey] ?? null;
            if (! is_array($detail)) {
                continue;
            }

            $accountQboId = QuickBooksRowMapper::refValue($detail['AccountRef'] ?? null);
            if ($accountQboId === '') {
                continue;
            }

            $chartOfAccountId = ChartOfAccount::query()->where('quickbooks_account_id', $accountQboId)->value('id');
            if ($chartOfAccountId !== null) {
                return (int) $chartOfAccountId;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function mapLineRows(mixed $lines): array
    {
        $mapped = [];
        $position = 0;

        foreach (QuickBooksRowMapper::normalizeList($lines) as $line) {
            if (! is_array($line)) {
                continue;
            }

            $detailType = QuickBooksRowMapper::normalizeString($line['DetailType'] ?? null);
            if ($detailType === '' || $detailType === 'SubTotalLineDetail') {
                continue;
            }

            $accountDetail = is_array($line['AccountBasedExpenseLineDetail'] ?? null)
                ? $line['AccountBasedExpenseLineDetail']
                : null;
            $itemDetail = is_array($line['ItemBasedExpenseLineDetail'] ?? null)
                ? $line['ItemBasedExpenseLineDetail']
                : null;

            $accountRef = is_array($accountDetail) ? ($accountDetail['AccountRef'] ?? null) : null;
            if (! is_array($accountRef) && is_array($itemDetail)) {
                $accountRef = $itemDetail['AccountRef'] ?? null;
            }

            $accountQboId = QuickBooksRowMapper::refValue(is_array($accountRef) ? $accountRef : null);
            $accountName = QuickBooksRowMapper::refName(is_array($accountRef) ? $accountRef : null);
            $chartOfAccountId = $accountQboId !== ''
                ? ChartOfAccount::query()->where('quickbooks_account_id', $accountQboId)->value('id')
                : null;

            $mapped[] = [
                'quickbooks_line_id' => QuickBooksRowMapper::refValue(['value' => $line['Id'] ?? null]) ?: null,
                'amount' => QuickBooksRowMapper::parseMoney($line['Amount'] ?? null),
                'description' => QuickBooksRowMapper::normalizeString($line['Description'] ?? null) ?: null,
                'detail_type' => $detailType,
                'chart_of_account_id' => $chartOfAccountId,
                'expense_account_ref_id' => $accountQboId !== '' ? $accountQboId : null,
                'expense_account_ref_name' => $accountName !== '' ? $accountName : null,
                'item_ref_id' => $itemDetail
                    ? QuickBooksRowMapper::refValue($itemDetail['ItemRef'] ?? null) ?: null
                    : null,
                'item_ref_name' => $itemDetail
                    ? QuickBooksRowMapper::refName($itemDetail['ItemRef'] ?? null) ?: null
                    : null,
                'quantity' => $itemDetail && isset($itemDetail['Qty']) ? (float) $itemDetail['Qty'] : null,
                'unit_price' => $itemDetail && isset($itemDetail['UnitPrice']) ? (float) $itemDetail['UnitPrice'] : null,
                'position' => $position++,
            ];
        }

        return $mapped;
    }

    /**
     * QuickBooks bills do not expose a status field; derive from balance/due date.
     * If a raw status value is present, normalize it to our stored string enum.
     *
     * @param  array<string, mixed>  $row
     */
    private static function resolveStatusFromQuickBooks(array $row, float $balance, ?Carbon $dueDate): string
    {
        $raw = $row['Status'] ?? $row['status'] ?? null;
        if ($raw !== null && $raw !== '') {
            $stored = BillStatus::toStoredValue($raw);
            if ($stored !== null) {
                return $stored;
            }
        }

        return BillStatusResolver::resolveValue($balance, $dueDate);
    }

    public static function billUrl(string $qboId): ?string
    {
        if ($qboId === '') {
            return null;
        }

        $host = config('services.quickbooks.environment') === 'production'
            ? 'https://qbo.intuit.com'
            : 'https://sandbox.qbo.intuit.com';

        return "{$host}/app/bill?txnId={$qboId}";
    }
}
