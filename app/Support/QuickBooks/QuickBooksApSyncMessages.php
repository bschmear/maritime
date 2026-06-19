<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

final class QuickBooksApSyncMessages
{
    public const LOADING = 'Bills and bill payments are synced with QuickBooks. Creating the record in QuickBooks and will import it when complete.';

    public static function failure(string $entityLabel, ?string $detail = null): string
    {
        $message = "QuickBooks sync failed. The {$entityLabel} was saved in Helmful, but we could not confirm it in QuickBooks.";

        if ($detail !== null && $detail !== '') {
            $message .= ' '.$detail;
        }

        return $message.' Please open QuickBooks Online and check whether the '.$entityLabel.' is there before trying again.';
    }
}
