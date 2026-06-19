<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;

final class QuickBooksPaymentAccountResolver
{
    public static function isBank(ChartOfAccount $account): bool
    {
        return self::normalizeType($account->account_type) === 'bank';
    }

    public static function isCreditCard(ChartOfAccount $account): bool
    {
        return self::normalizeType($account->account_type) === 'credit card';
    }

    public static function isAccountsPayable(ChartOfAccount $account): bool
    {
        return self::normalizeType($account->account_type) === 'accounts payable';
    }

    public static function findByQuickbooksId(?string $qboId): ?ChartOfAccount
    {
        $qboId = trim((string) ($qboId ?? ''));
        if ($qboId === '') {
            return null;
        }

        return ChartOfAccount::query()
            ->where('quickbooks_account_id', $qboId)
            ->first();
    }

    public static function resolveBankAccount(?string $preferredQboId = null): ?ChartOfAccount
    {
        $preferred = self::findByQuickbooksId($preferredQboId);
        if ($preferred !== null && self::isBank($preferred)) {
            return $preferred;
        }

        return ChartOfAccount::query()
            ->whereNotNull('quickbooks_account_id')
            ->where('active', true)
            ->whereRaw("LOWER(TRIM(COALESCE(account_type, ''))) = ?", ['bank'])
            ->orderBy('fully_qualified_name')
            ->orderBy('name')
            ->first();
    }

    public static function resolveCreditCardAccount(?string $preferredQboId = null): ?ChartOfAccount
    {
        $preferred = self::findByQuickbooksId($preferredQboId);
        if ($preferred !== null && self::isCreditCard($preferred)) {
            return $preferred;
        }

        return ChartOfAccount::query()
            ->whereNotNull('quickbooks_account_id')
            ->where('active', true)
            ->whereRaw("LOWER(TRIM(COALESCE(account_type, ''))) = ?", ['credit card'])
            ->orderBy('fully_qualified_name')
            ->orderBy('name')
            ->first();
    }

    /**
     * Resolve the QuickBooks account id to use for a check bill payment.
     */
    public static function validatedBankQuickbooksId(?string $preferredQboId = null): ?string
    {
        $account = self::resolveBankAccount($preferredQboId);

        return filled($account?->quickbooks_account_id)
            ? (string) $account->quickbooks_account_id
            : null;
    }

    /**
     * Resolve the QuickBooks account id to use for a credit card bill payment.
     */
    public static function validatedCreditCardQuickbooksId(?string $preferredQboId = null): ?string
    {
        $account = self::resolveCreditCardAccount($preferredQboId);

        return filled($account?->quickbooks_account_id)
            ? (string) $account->quickbooks_account_id
            : null;
    }

    public static function validatedAccountsPayableQuickbooksId(?string $preferredQboId = null): ?string
    {
        $preferred = self::findByQuickbooksId($preferredQboId);
        if ($preferred !== null && self::isAccountsPayable($preferred)) {
            return (string) $preferred->quickbooks_account_id;
        }

        return null;
    }

    private static function normalizeType(?string $type): string
    {
        return strtolower(trim((string) ($type ?? '')));
    }
}
