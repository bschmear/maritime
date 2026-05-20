<?php

namespace App\Services\SMS\Providers;

use App\Services\SMS\Contracts\SmsProviderInterface;
use App\Services\SMS\Data\SmsResult;

class TwilioProvider implements SmsProviderInterface
{
    public function send(
        string $to,
        string $message,
        ?string $from = null,
    ): SmsResult {
        return new SmsResult(
            success: false,
            status: 'not_implemented',
            error: 'Twilio transport is not wired yet; queue a send job from SmsService.',
        );
    }
}
