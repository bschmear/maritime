<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Support\QuickBooks\QuickBooksImportStatus;
use Throwable;

trait MarksQuickBooksImportFailure
{
    public function failed(?Throwable $exception): void
    {
        QuickBooksImportStatus::markFailed($exception?->getMessage());
    }
}
