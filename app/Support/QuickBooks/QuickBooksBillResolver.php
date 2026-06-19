<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\Bill\Models\Bill;

final class QuickBooksBillResolver
{
    public static function resolveLocalBillId(?string $quickbooksBillId): ?int
    {
        if ($quickbooksBillId === null || $quickbooksBillId === '') {
            return null;
        }

        $id = Bill::query()->where('quickbooks_bill_id', $quickbooksBillId)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
