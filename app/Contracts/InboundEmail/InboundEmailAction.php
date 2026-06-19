<?php

namespace App\Contracts\InboundEmail;

use App\Models\AiEmailIngestion;
use App\Models\EmailRoute;

interface InboundEmailAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(AiEmailIngestion $ingestion, EmailRoute $route): array;
}
