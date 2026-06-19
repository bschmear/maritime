<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

final class QuickBooksVendorMapper
{
    /**
     * Map a QuickBooks Online Vendor entity row to a vendors table payload.
     *
     * ACH details live on the vendor as {@see VendorPaymentBankDetail} (API minor version 40+).
     * Open balance is {@see Balance}. 1099 tracking is {@see Vendor1099}.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function mapVendorRow(array $row): array
    {
        $qboId = QuickBooksRowMapper::refValue(['value' => $row['Id'] ?? null]);
        $displayName = QuickBooksRowMapper::normalizeString($row['DisplayName'] ?? null);
        $companyName = QuickBooksRowMapper::normalizeString($row['CompanyName'] ?? null);

        if ($displayName === '' && $companyName !== '') {
            $displayName = $companyName;
        }

        $email = QuickBooksRowMapper::normalizeEmail($row['PrimaryEmailAddr']['Address'] ?? null);
        $phone = QuickBooksRowMapper::normalizeString($row['PrimaryPhone']['FreeFormNumber'] ?? null);
        $mobile = QuickBooksRowMapper::normalizeString($row['Mobile']['FreeFormNumber'] ?? null);
        $fax = QuickBooksRowMapper::normalizeString($row['Fax']['FreeFormNumber'] ?? null);
        $billAddr = is_array($row['BillAddr'] ?? null) ? $row['BillAddr'] : [];
        $bankDetail = is_array($row['VendorPaymentBankDetail'] ?? null) ? $row['VendorPaymentBankDetail'] : [];

        $achBankName = QuickBooksRowMapper::normalizeString($bankDetail['BankAccountName'] ?? null);
        $achAccountNumber = QuickBooksRowMapper::normalizeString($bankDetail['BankAccountNumber'] ?? null);
        $achRoutingNumber = QuickBooksRowMapper::normalizeString($bankDetail['BankBranchIdentifier'] ?? null);

        $taxId = QuickBooksRowMapper::normalizeString($row['TaxIdentifier'] ?? null);
        $acctNum = QuickBooksRowMapper::normalizeString($row['AcctNum'] ?? null);
        $printOnCheck = QuickBooksRowMapper::normalizeString($row['PrintOnCheckName'] ?? null);
        $website = QuickBooksRowMapper::normalizeString($row['WebAddr']['URI'] ?? $row['WebAddr'] ?? null);

        $givenName = QuickBooksRowMapper::normalizeString($row['GivenName'] ?? null);
        $familyName = QuickBooksRowMapper::normalizeString($row['FamilyName'] ?? null);
        $title = QuickBooksRowMapper::normalizeString($row['Title'] ?? null);

        $payload = [
            'display_name' => $displayName,
            'company_name' => $companyName !== '' ? $companyName : null,
            'print_on_check_name' => $printOnCheck !== '' ? $printOnCheck : null,
            'quickbooks_id' => $qboId !== '' ? $qboId : null,
            'quickbooks_sync_token' => QuickBooksRowMapper::normalizeString($row['SyncToken'] ?? null) ?: null,
            'qbo_acct_num' => $acctNum !== '' ? $acctNum : null,
            'qbo_active' => ! (array_key_exists('Active', $row) && $row['Active'] === false),
            'open_balance' => QuickBooksRowMapper::parseMoney($row['Balance'] ?? 0),
            'vendor_1099' => (bool) ($row['Vendor1099'] ?? false),
            'term_ref_id' => QuickBooksRowMapper::refValue($row['TermRef'] ?? null) ?: null,
            'term_ref_name' => QuickBooksRowMapper::refName($row['TermRef'] ?? null) ?: null,
            'contact_email' => $email,
            'contact_phone' => $phone !== '' ? $phone : null,
            'mobile_phone' => $mobile !== '' ? $mobile : null,
            'fax' => $fax !== '' ? $fax : null,
            'contact_title' => $title !== '' ? $title : null,
            'contact_first_name' => $givenName !== '' ? $givenName : null,
            'contact_last_name' => $familyName !== '' ? $familyName : null,
            'website' => $website !== '' ? $website : null,
            'address_line_1' => QuickBooksRowMapper::normalizeString($billAddr['Line1'] ?? null) ?: null,
            'address_line_2' => QuickBooksRowMapper::normalizeString($billAddr['Line2'] ?? null) ?: null,
            'city' => QuickBooksRowMapper::normalizeString($billAddr['City'] ?? null) ?: null,
            'state' => QuickBooksRowMapper::normalizeString($billAddr['CountrySubDivisionCode'] ?? null) ?: null,
            'postal_code' => QuickBooksRowMapper::normalizeString($billAddr['PostalCode'] ?? null) ?: null,
            'country' => QuickBooksRowMapper::normalizeString($billAddr['Country'] ?? null) ?: null,
            'ach_bank_name' => $achBankName !== '' ? $achBankName : null,
        ];

        if ($achAccountNumber !== '') {
            $payload['ach_account_number'] = $achAccountNumber;
        }

        if ($achRoutingNumber !== '') {
            $payload['ach_routing_number'] = $achRoutingNumber;
        }

        if ($taxId !== '') {
            $payload['tax_identifier'] = $taxId;
        }

        return $payload;
    }

    /**
     * Merge bank / tax fields from a single-vendor read when the list query omits them.
     *
     * @param  array<string, mixed>  $queryRow
     * @param  array<string, mixed>  $readRow
     * @return array<string, mixed>
     */
    public static function mergeReadRow(array $queryRow, array $readRow): array
    {
        foreach (['VendorPaymentBankDetail', 'TaxIdentifier'] as $key) {
            if (self::rowValueIsEmpty($queryRow[$key] ?? null) && ! self::rowValueIsEmpty($readRow[$key] ?? null)) {
                $queryRow[$key] = $readRow[$key];
            }
        }

        return $queryRow;
    }

    private static function rowValueIsEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    /**
     * Do not overwrite encrypted ACH / tax fields when QuickBooks omits them from the response.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function preserveSensitiveFieldsWhenAbsent(array $payload): array
    {
        foreach (['ach_account_number', 'ach_routing_number', 'tax_identifier'] as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                unset($payload[$key]);
            }
        }

        return $payload;
    }
}
