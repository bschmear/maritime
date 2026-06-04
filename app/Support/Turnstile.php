<?php

declare(strict_types=1);

namespace App\Support;

use App\Rules\ValidTurnstile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile server-side verification.
 *
 * @see https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 */
final class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function isConfigured(): bool
    {
        $secret = (string) config('services.turnstile.secret_key', '');

        return trim($secret) !== '';
    }

    public static function siteKey(): ?string
    {
        if (! self::isConfigured()) {
            return null;
        }

        $key = trim((string) config('services.turnstile.site_key', ''));

        return $key !== '' ? $key : null;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public static function validationRules(): array
    {
        return [
            'turnstile_token' => [
                self::isConfigured() ? 'required' : 'nullable',
                'string',
                new ValidTurnstile,
            ],
        ];
    }

    public static function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! self::isConfigured()) {
            return true;
        }

        if ($token === null || trim($token) === '') {
            return false;
        }

        try {
            $payload = [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
            ];

            if (config('services.turnstile.send_remote_ip') && $remoteIp !== null && $remoteIp !== '') {
                $payload['remoteip'] = $remoteIp;
            }

            $response = Http::asForm()
                ->timeout(10)
                ->post(self::VERIFY_URL, $payload);

            if ($response->failed()) {
                Log::warning('Turnstile siteverify HTTP failure', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $json = $response->json();
            if (! is_array($json)) {
                return false;
            }

            if (! ($json['success'] ?? false)) {
                Log::info('Turnstile verification rejected', [
                    'error_codes' => $json['error-codes'] ?? [],
                ]);
            }

            return (bool) ($json['success'] ?? false);
        } catch (\Throwable $e) {
            Log::warning('Turnstile siteverify threw', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
