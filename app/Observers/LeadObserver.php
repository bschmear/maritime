<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Lead\Models\Lead;
use App\Support\Tenant\LeadPipelineCountCache;

class LeadObserver
{
    public function saved(Lead $lead): void
    {
        LeadPipelineCountCache::forget();
    }

    public function deleted(Lead $lead): void
    {
        LeadPipelineCountCache::forget();
    }
}
