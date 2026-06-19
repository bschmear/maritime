<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;

final class QuickBooksChartOfAccountResolver
{
    public static function resolveLocalIdByQuickbooksAccountId(?string $quickbooksAccountId): ?int
    {
        $qboId = is_string($quickbooksAccountId) ? trim($quickbooksAccountId) : '';
        if ($qboId === '') {
            return null;
        }

        $localId = ChartOfAccount::query()
            ->where('quickbooks_account_id', $qboId)
            ->value('id');

        return $localId !== null ? (int) $localId : null;
    }

    /**
     * @return array{id: int, name: string, fully_qualified_name: string|null, display_name: string}|null
     */
    public static function resolveSummaryByQuickbooksAccountId(?string $quickbooksAccountId): ?array
    {
        $qboId = is_string($quickbooksAccountId) ? trim($quickbooksAccountId) : '';
        if ($qboId === '') {
            return null;
        }

        $account = ChartOfAccount::query()
            ->where('quickbooks_account_id', $qboId)
            ->first(['id', 'name', 'fully_qualified_name']);

        if ($account === null) {
            return null;
        }

        return [
            'id' => (int) $account->id,
            'name' => (string) $account->name,
            'fully_qualified_name' => $account->fully_qualified_name,
            'display_name' => $account->display_name,
        ];
    }
}
