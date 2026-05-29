<?php

declare(strict_types=1);

namespace App\Services\Payments;

use Illuminate\Http\Client\Response;

/**
 * Helpers for Intuit / QuickBooks Online HTTP responses.
 *
 * @see https://developer.intuit.com/app/developer/qbo/docs/develop/troubleshooting
 */
final class QuickBooksHttpSupport
{
    /** @var list<string> */
    private const TID_HEADER_NAMES = [
        'intuit_tid',
        'intuit-tid',
        'Intuit-Tid',
        'INTUIT_TID',
    ];

    public static function intuitTid(Response $response): ?string
    {
        foreach (self::TID_HEADER_NAMES as $name) {
            $value = $response->header($name);

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Merge {@see intuitTid()} into log context when present.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function withIntuitTid(Response $response, array $context = []): array
    {
        $tid = self::intuitTid($response);
        if ($tid !== null) {
            $context['intuit_tid'] = $tid;
        }

        return $context;
    }
}
