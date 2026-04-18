<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Integration\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OAuth token refresh for Mailchimp (shared by tenant controllers and queue jobs).
 */
class MailchimpOAuthService
{
    public function refreshAccessToken(Integration $integration): string
    {
        $refreshToken = $integration->refresh_token;

        if (! $refreshToken) {
            throw new \RuntimeException('No refresh token available. User needs to re-authenticate.');
        }

        try {
            $response = Http::asForm()->post('https://login.mailchimp.com/oauth2/token', [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.mailchimp.client_id'),
                'client_secret' => config('services.mailchimp.client_secret'),
                'refresh_token' => $refreshToken,
            ]);

            if ($response->failed()) {
                Log::error('Mailchimp token refresh failed', ['response' => $response->body()]);
                throw new \RuntimeException('Failed to refresh access token. User needs to re-authenticate.');
            }

            $data = $response->json();

            if (! isset($data['access_token'])) {
                throw new \RuntimeException('No access token in refresh response');
            }

            $integration->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                'token_expires_at' => isset($data['expires_in']) && $data['expires_in'] > 0
                    ? now()->addSeconds((int) $data['expires_in'])
                    : null,
            ]);

            return $data['access_token'];
        } catch (\Throwable $e) {
            Log::error('Mailchimp token refresh error', ['exception' => $e]);
            throw $e;
        }
    }
}
