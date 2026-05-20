<?php

namespace App\Services\SMS;

use App\Services\SMS\Contracts\SmsProviderInterface;
use App\Services\SMS\Providers\TwilioProvider;
use InvalidArgumentException;

class SmsProviderFactory
{
    public static function make(): SmsProviderInterface
    {
        return match (config('sms.default')) {
            'twilio' => app(TwilioProvider::class),
            default => throw new InvalidArgumentException(
                'Unsupported SMS provider: '.(string) config('sms.default'),
            ),
        };
    }
}
