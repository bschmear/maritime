<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;

final class ChartOfAccountImportMatcher
{
    /**
     * Find an existing Helmful chart of account that matches a QuickBooks import row.
     *
     * @param  array<string, mixed>  $accountPayload
     */
    public static function findExisting(array $accountPayload): ?ChartOfAccount
    {
        $qboId = trim((string) ($accountPayload['quickbooks_account_id'] ?? ''));
        if ($qboId !== '') {
            $byQboId = ChartOfAccount::query()->where('quickbooks_account_id', $qboId)->first();
            if ($byQboId !== null) {
                return $byQboId;
            }
        }

        $fullyQualifiedName = self::normalizeFqn((string) ($accountPayload['fully_qualified_name'] ?? ''));
        if ($fullyQualifiedName !== '') {
            $byFqn = ChartOfAccount::query()
                ->whereRaw('LOWER(fully_qualified_name) = ?', [strtolower($fullyQualifiedName)])
                ->first();
            if ($byFqn !== null) {
                return $byFqn;
            }
        }

        $name = trim((string) ($accountPayload['name'] ?? ''));
        $accountType = trim((string) ($accountPayload['account_type'] ?? ''));
        $detailType = trim((string) ($accountPayload['detail_type'] ?? ''));

        if ($name === '' || $accountType === '' || $fullyQualifiedName !== $name) {
            return null;
        }

        return ChartOfAccount::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->where('account_type', $accountType)
            ->when(
                $detailType !== '',
                static fn ($query) => $query->where('detail_type', $detailType),
                static fn ($query) => $query->whereNull('detail_type'),
            )
            ->whereNull('parent_id')
            ->where(function ($query) use ($qboId): void {
                $query->whereNull('quickbooks_account_id');
                if ($qboId !== '') {
                    $query->orWhere('quickbooks_account_id', $qboId);
                }
            })
            ->first();
    }

    private static function normalizeFqn(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
