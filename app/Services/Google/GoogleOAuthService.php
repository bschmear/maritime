<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationType;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleOAuthService
{
    private const AUTH_BASE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        $raw = (string) config('services.google.scopes', '');
        if ($raw === '') {
            return [
                'https://www.googleapis.com/auth/drive.file',
                'https://www.googleapis.com/auth/spreadsheets',
                'https://www.googleapis.com/auth/userinfo.email',
            ];
        }

        $parts = preg_split('/[\s,]+/', trim($raw)) ?: [];

        return array_values(array_filter($parts, fn (string $scope) => $scope !== ''));
    }

    public function authorizeUrl(string $state, string $redirectUri): string
    {
        $params = [
            'client_id' => (string) config('services.google.client_id'),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes()),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return self::AUTH_BASE_URL.'?'.http_build_query($params);
    }

    /**
     * @return array{access_token: string, refresh_token?: string, expires_in: int, token_type?: string, scope?: string}
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'client_id' => (string) config('services.google.client_id'),
                'client_secret' => (string) config('services.google.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if (! $response->successful()) {
            Log::error('Google OAuth code exchange failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Google did not return a token.');
        }

        return $response->json();
    }

    /**
     * @return array{access_token: string, refresh_token?: string, expires_in: int}
     */
    public function refreshAccessToken(Integration $integration): array
    {
        if (! $integration->refresh_token) {
            throw new RuntimeException('No Google refresh token on file — reconnect required.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'client_id' => (string) config('services.google.client_id'),
                'client_secret' => (string) config('services.google.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $integration->refresh_token,
            ]);

        if (! $response->successful()) {
            Log::error('Google OAuth refresh failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Google refresh token rejected — reconnect required.');
        }

        return $response->json();
    }

    public function revokeToken(?string $token): void
    {
        if (! filled($token)) {
            return;
        }

        Http::asForm()->post(self::REVOKE_URL, ['token' => $token]);
    }

    public function persistConnection(int $userId, array $tokenPayload, ?string $googleAccountId = null, ?string $email = null): Integration
    {
        $expiresIn = (int) ($tokenPayload['expires_in'] ?? 3600);

        $attributes = [
            'user_id' => $userId,
            'integration_type' => IntegrationType::Google,
            'name' => IntegrationType::Google->label(),
            'access_token' => $tokenPayload['access_token'],
            'token_expires_at' => now()->addSeconds(max(60, $expiresIn)),
            'active' => true,
            'metadata' => array_filter([
                'google_email' => $email,
                'connected_at' => now()->toIso8601String(),
            ]),
        ];

        if (! empty($tokenPayload['refresh_token'])) {
            $attributes['refresh_token'] = $tokenPayload['refresh_token'];
        }

        if ($googleAccountId !== null) {
            $attributes['external_id'] = $googleAccountId;
        }

        return Integration::upsertFromOAuth(
            ['integration_type' => IntegrationType::Google],
            $attributes,
        );
    }

    public function integration(): ?Integration
    {
        return Integration::query()
            ->where('integration_type', IntegrationType::Google)
            ->where('active', true)
            ->first();
    }

    public function hasCredentials(): bool
    {
        $integration = $this->integration();

        return $integration !== null
            && filled($integration->access_token)
            && filled($integration->refresh_token);
    }

    public function clientForIntegration(Integration $integration): GoogleClient
    {
        if ($integration->token_expires_at !== null && $integration->token_expires_at->isPast()) {
            $refreshed = $this->refreshAccessToken($integration);
            $integration->updateOAuthTokens([
                'access_token' => $refreshed['access_token'],
                'refresh_token' => $refreshed['refresh_token'] ?? $integration->refresh_token,
                'token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 3600)),
            ]);
            $integration->refresh();
        }

        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setAccessToken([
            'access_token' => $integration->access_token,
            'refresh_token' => $integration->refresh_token,
            'expires_in' => max(0, now()->diffInSeconds($integration->token_expires_at ?? now()->addHour(), false)),
            'created' => now()->subHour()->getTimestamp(),
        ]);

        return $client;
    }

    public function fetchUserEmail(string $accessToken): ?string
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (! $response->successful()) {
            return null;
        }

        return $response->json('email');
    }
}
