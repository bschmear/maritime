<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Enums\BillPayment\PayType;

final class QuickBooksBillPaymentMapper
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function mapBillPaymentRow(array $row): array
    {
        $qboId = QuickBooksRowMapper::refValue(['value' => $row['Id'] ?? null]);
        $vendorRef = is_array($row['VendorRef'] ?? null) ? $row['VendorRef'] : null;
        $vendorQboId = QuickBooksVendorResolver::quickbooksVendorIdFromRef($vendorRef);
        $vendorId = QuickBooksVendorResolver::resolveLocalVendorId($vendorRef);

        $checkPayment = is_array($row['CheckPayment'] ?? null) ? $row['CheckPayment'] : null;
        $ccPayment = is_array($row['CreditCardPayment'] ?? null) ? $row['CreditCardPayment'] : null;

        return [
            'quickbooks_bill_payment_id' => $qboId,
            'quickbooks_sync_token' => QuickBooksRowMapper::normalizeString($row['SyncToken'] ?? null) ?: null,
            'vendor_id' => $vendorId,
            'quickbooks_vendor_id' => $vendorQboId,
            'doc_number' => QuickBooksRowMapper::normalizeString($row['DocNumber'] ?? null) ?: null,
            'txn_date' => ! empty($row['TxnDate']) ? (string) $row['TxnDate'] : null,
            'total_amt' => QuickBooksRowMapper::parseMoney($row['TotalAmt'] ?? null),
            'pay_type' => PayType::fromQuickBooks(
                QuickBooksRowMapper::normalizeString($row['PayType'] ?? null),
            )->value,
            'ap_account_ref_id' => QuickBooksRowMapper::refValue($row['APAccountRef'] ?? null) ?: null,
            'ap_account_ref_name' => QuickBooksRowMapper::refName($row['APAccountRef'] ?? null) ?: null,
            'bank_account_ref_id' => $checkPayment
                ? QuickBooksRowMapper::refValue($checkPayment['BankAccountRef'] ?? null) ?: null
                : null,
            'bank_account_ref_name' => $checkPayment
                ? QuickBooksRowMapper::refName($checkPayment['BankAccountRef'] ?? null) ?: null
                : null,
            'cc_account_ref_id' => $ccPayment
                ? QuickBooksRowMapper::refValue($ccPayment['CCAccountRef'] ?? null) ?: null
                : null,
            'cc_account_ref_name' => $ccPayment
                ? QuickBooksRowMapper::refName($ccPayment['CCAccountRef'] ?? null) ?: null
                : null,
            'check_print_status' => $checkPayment
                ? QuickBooksRowMapper::normalizeString($checkPayment['PrintStatus'] ?? null) ?: null
                : null,
            'currency_code' => QuickBooksRowMapper::refValue($row['CurrencyRef'] ?? null) ?: 'USD',
            'exchange_rate' => isset($row['ExchangeRate']) ? (float) $row['ExchangeRate'] : null,
            'private_note' => QuickBooksRowMapper::normalizeString($row['PrivateNote'] ?? null) ?: null,
            'lines' => self::mapPaymentLines($row['Line'] ?? []),
            'for_import' => true,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function mapPaymentLines(mixed $lines): array
    {
        $mapped = [];
        $position = 0;

        foreach (QuickBooksRowMapper::normalizeList($lines) as $line) {
            if (! is_array($line)) {
                continue;
            }

            $amount = QuickBooksRowMapper::parseMoney($line['Amount'] ?? null);
            $billQboId = '';
            $billId = null;

            foreach (QuickBooksRowMapper::normalizeList($line['LinkedTxn'] ?? []) as $txn) {
                if (! is_array($txn)) {
                    continue;
                }
                if (($txn['TxnType'] ?? '') === 'Bill') {
                    $billQboId = QuickBooksRowMapper::refValue(['value' => $txn['TxnId'] ?? null]);
                    if ($billQboId !== '') {
                        $billId = QuickBooksBillResolver::resolveLocalBillId($billQboId);
                    }
                    break;
                }
            }

            $mapped[] = [
                'bill_id' => $billId,
                'quickbooks_bill_id' => $billQboId !== '' ? $billQboId : null,
                'amount' => $amount,
                'position' => $position++,
            ];
        }

        return $mapped;
    }
}
