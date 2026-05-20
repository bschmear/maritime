<?php

namespace App\Services\SMS\Data;

class SmsResult
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public ?string $status = null,
        public ?string $error = null,
    ) {}
}
