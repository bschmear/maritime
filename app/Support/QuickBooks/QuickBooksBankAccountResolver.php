<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

use App\Domain\ChartOfAccount\Models\ChartOfAccount;

final class QuickBooksBankAccountResolver
{
    public static function resolve(): ?ChartOfAccount
    {
        return QuickBooksPaymentAccountResolver::resolveBankAccount();
    }

    public static function resolveQuickbooksBankAccountId(): ?string
    {
        return QuickBooksPaymentAccountResolver::validatedBankQuickbooksId();
    }
}
