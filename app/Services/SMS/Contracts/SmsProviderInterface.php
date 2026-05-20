<?php

namespace App\Services\SMS\Contracts;

use App\Services\SMS\Data\SmsResult;

interface SmsProviderInterface
{
    public function send(
        string $to,
        string $message,
        ?string $from = null,
    ): SmsResult;
}
