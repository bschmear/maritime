<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;

final class QuickBooksBankAccountResolver
{
    public static function resolve(): ?ChartOfAccount
    {
        return ChartOfAccount::query()
            ->whereNotNull('quickbooks_account_id')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(COALESCE(account_type, \'\')) LIKE ?', ['%bank%'])
                    ->orWhereRaw('LOWER(COALESCE(detail_type, \'\')) LIKE ?', ['%bank%'])
                    ->orWhereRaw('LOWER(COALESCE(detail_type, \'\')) LIKE ?', ['%checking%']);
            })
            ->orderBy('fully_qualified_name')
            ->orderBy('name')
            ->first();
    }

    public static function resolveQuickbooksBankAccountId(): ?string
    {
        $account = self::resolve();

        return filled($account?->quickbooks_account_id)
            ? (string) $account->quickbooks_account_id
            : null;
    }
}
