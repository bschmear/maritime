<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountSetupStepStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Skipped = 'skipped';

    public function isResolved(): bool
    {
        return $this !== self::Pending;
    }
}
