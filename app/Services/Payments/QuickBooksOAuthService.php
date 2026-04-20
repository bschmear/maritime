<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Domain\Integration\Models\Integration;
use App\Domain\Payment\Models\PaymentConfiguration;
use App\Enums\Integration\IntegrationType;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * QuickBooks Online OAuth 2.0 helper (authorize URL, code exchange, token refresh, persist).
 *
 * Endpoints are stable; auth host is the same for sandbox and production. Only the data API
 * host differs (handled here for the small post-connect company-info call).
 *
 * Reference: https://developer.intuit.com/app/developer/qbo/docs/develop/authentication-and-authorization
 */
class QuickBooksOAuthService
{
    private const AUTH_BASE_URL = 'https://appcenter.intuit.com/connect/oauth2';

    private const TOKEN_URL = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';

    private const REVOKE_URL = 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke';

    public function authorizeUrl(string $state, string $redirectUri): string
    {
        $params = [
            'client_id' => (string) config('services.quickbooks.client_id'),
            'response_type' => 'code',
            'scope' => (string) config('services.quickbooks.scopes', 'com.intuit.quickbooks.accounting'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];

        return self::AUTH_BASE_URL.'?'.http_build_query($params);
    }

    /**
     * Exchange the authorization code for access + refresh tokens.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, x_refresh_token_expires_in?: int, token_type?: string}
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.quickbooks.client_id'),
                (string) config('services.quickbooks.client_secret'),
            )
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        $this->guardTokenResponse($response, 'authorization_code');

        return $response->json();
    }

    /**
     * Refresh the configuration's access token in-place. No-op when nothing is connected.
     *
     * @throws RuntimeException when Intuit rejects the refresh and the config must be reconnected
     */
    public function refreshAccessToken(PaymentConfiguration $config): void
    {
        if (! $config->qbo_refresh_token_enc) {
            throw new RuntimeException('No QuickBooks refresh token on file — reconnect required.');
        }

        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.quickbooks.client_id'),
                (string) config('services.quickbooks.client_secret'),
            )
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $config->qbo_refresh_token_enc,
            ]);

        $this->guardTokenResponse($response, 'refresh_token');

        $data = $response->json();

        $config->update([
            'qbo_access_token_enc' => $data['access_token'],
            'qbo_refresh_token_enc' => $data['refresh_token'] ?? $config->qbo_refresh_token_enc,
            'qbo_token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
            'meta' => array_merge($config->meta ?? [], [
                'qbo_last_refreshed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Persist tokens + realm id from a fresh authorization-code exchange.
     *
     * @param  array{access_token: string, refresh_token: string, expires_in?: int, x_refresh_token_expires_in?: int}  $tokens
     */
    public function persistConnection(PaymentConfiguration $config, string $realmId, array $tokens, ?array $companyInfo = null): void
    {
        $now = now();

        $meta = array_merge($config->meta ?? [], [
            'qbo_environment' => (string) config('services.quickbooks.environment', 'sandbox'),
            'qbo_connected_at' => $now->toIso8601String(),
            'qbo_company_name' => $companyInfo['CompanyName'] ?? ($companyInfo['CompanyInfo']['CompanyName'] ?? null),
            'qbo_legal_name' => $companyInfo['LegalName'] ?? ($companyInfo['CompanyInfo']['LegalName'] ?? null),
            'qbo_country' => $companyInfo['Country'] ?? ($companyInfo['CompanyInfo']['Country'] ?? null),
            'qbo_email' => $companyInfo['Email']['Address']
                ?? ($companyInfo['CompanyInfo']['Email']['Address'] ?? null),
            'qbo_refresh_token_expires_at' => isset($tokens['x_refresh_token_expires_in'])
                ? $now->copy()->addSeconds((int) $tokens['x_refresh_token_expires_in'])->toIso8601String()
                : ($config->meta['qbo_refresh_token_expires_at'] ?? null),
        ]);

        $config->update([
            'qbo_realm_id' => $realmId,
            'qbo_access_token_enc' => $tokens['access_token'],
            'qbo_refresh_token_enc' => $tokens['refresh_token'],
            'qbo_token_expires_at' => $now->copy()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            'meta' => $meta,
        ]);
    }

    /**
     * Persist OAuth tokens on an {@see Integration} row (Integrations → QuickBooks path).
     *
     * @param  array{access_token: string, refresh_token: string, expires_in?: int, x_refresh_token_expires_in?: int}  $tokens
     */
    public function persistConnectionToIntegration(
        int $userProfileId,
        string $realmId,
        array $tokens,
        ?array $companyInfo = null,
    ): Integration {
        $now = now();

        $meta = [
            'qbo_environment' => (string) config('services.quickbooks.environment', 'sandbox'),
            'qbo_connected_at' => $now->toIso8601String(),
            'qbo_company_name' => $companyInfo['CompanyName'] ?? ($companyInfo['CompanyInfo']['CompanyName'] ?? null),
            'qbo_legal_name' => $companyInfo['LegalName'] ?? ($companyInfo['CompanyInfo']['LegalName'] ?? null),
            'qbo_country' => $companyInfo['Country'] ?? ($companyInfo['CompanyInfo']['Country'] ?? null),
            'qbo_email' => $companyInfo['Email']['Address']
                ?? ($companyInfo['CompanyInfo']['Email']['Address'] ?? null),
            'qbo_refresh_token_expires_at' => isset($tokens['x_refresh_token_expires_in'])
                ? $now->copy()->addSeconds((int) $tokens['x_refresh_token_expires_in'])->toIso8601String()
                : null,
        ];

        /** @var Integration */
        return Integration::query()->updateOrCreate(
            [
                'integration_type' => (string) IntegrationType::QuickBooks->value,
                'external_id' => $realmId,
            ],
            [
                'user_id' => $userProfileId,
                'name' => IntegrationType::QuickBooks->label(),
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? '',
                'token_expires_at' => $now->copy()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
                'metadata' => $meta,
                'active' => true,
                'sync_error_message' => null,
            ]
        );
    }

    /**
     * @throws RuntimeException when Intuit rejects the refresh
     */
    public function refreshAccessTokenForIntegration(Integration $integration): void
    {
        if (! $integration->refresh_token) {
            throw new RuntimeException('No QuickBooks refresh token on file — reconnect required.');
        }

        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.quickbooks.client_id'),
                (string) config('services.quickbooks.client_secret'),
            )
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $integration->refresh_token,
            ]);

        $this->guardTokenResponse($response, 'refresh_token');

        $data = $response->json();

        $integration->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $integration->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
            'metadata' => array_merge($integration->metadata ?? [], [
                'qbo_last_refreshed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Best-effort fetch of the connected company's basic info (name, country, email).
     *
     * @return array<string, mixed>|null
     */
    public function fetchCompanyInfo(string $accessToken, string $realmId): ?array
    {
        $base = $this->isProduction()
            ? 'https://quickbooks.api.intuit.com'
            : 'https://sandbox-quickbooks.api.intuit.com';

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->get("{$base}/v3/company/{$realmId}/companyinfo/{$realmId}", [
                    'minorversion' => '70',
                ]);

            if ($response->failed()) {
                Log::warning('QuickBooks companyinfo fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $payload = $response->json();

            return $payload['CompanyInfo'] ?? $payload;
        } catch (\Throwable $e) {
            Log::warning('QuickBooks companyinfo fetch threw', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Revoke a token at Intuit. Pass the refresh token when available (preferred) so future
     * access tokens minted from it are also invalidated. Errors are logged and swallowed.
     */
    public function revoke(string $token): void
    {
        try {
            $response = Http::withBasicAuth(
                (string) config('services.quickbooks.client_id'),
                (string) config('services.quickbooks.client_secret'),
            )
                ->acceptJson()
                ->asJson()
                ->post(self::REVOKE_URL, ['token' => $token]);

            if ($response->failed()) {
                Log::warning('QuickBooks revoke returned non-success', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('QuickBooks revoke threw', ['error' => $e->getMessage()]);
        }
    }

    public function isProduction(): bool
    {
        return strtolower((string) config('services.quickbooks.environment', 'sandbox')) === 'production';
    }

    /**
     * Base URL for the QuickBooks Online Accounting Data API (company queries, entities).
     */
    public function accountingApiBaseUrl(): string
    {
        return $this->isProduction()
            ? 'https://quickbooks.api.intuit.com'
            : 'https://sandbox-quickbooks.api.intuit.com';
    }

    /**
     * Run a QBO SQL-style query (e.g. {@code select * from Customer STARTPOSITION 1 MAXRESULTS 100}).
     *
     * @return array<string, mixed>
     */
    public function queryAccounting(PaymentConfiguration $config, string $qboSql): array
    {
        $this->refreshAccessTokenIfExpired($config);

        $realmId = $config->qbo_realm_id;
        if ($realmId === null || $realmId === '') {
            throw new RuntimeException('QuickBooks realm id is missing — reconnect QuickBooks.');
        }

        $response = Http::withToken($config->qbo_access_token_enc)
            ->acceptJson()
            ->get("{$this->accountingApiBaseUrl()}/v3/company/{$realmId}/query", [
                'query' => $qboSql,
                'minorversion' => 70,
            ]);

        if ($response->failed()) {
            Log::error('QuickBooks accounting query failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('QuickBooks query failed (HTTP '.$response->status().').');
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function queryAccountingForIntegration(Integration $integration, string $qboSql): array
    {
        $this->refreshAccessTokenIfExpiredForIntegration($integration);

        $realmId = (string) $integration->external_id;
        if ($realmId === '') {
            throw new RuntimeException('QuickBooks realm id is missing — reconnect QuickBooks.');
        }

        $response = Http::withToken($integration->access_token)
            ->acceptJson()
            ->get("{$this->accountingApiBaseUrl()}/v3/company/{$realmId}/query", [
                'query' => $qboSql,
                'minorversion' => 70,
            ]);

        if ($response->failed()) {
            Log::error('QuickBooks accounting query failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('QuickBooks query failed (HTTP '.$response->status().').');
        }

        return $response->json();
    }

    /**
     * Refresh the access token when it is missing or near expiry so API calls succeed.
     */
    public function refreshAccessTokenIfExpired(PaymentConfiguration $config): void
    {
        if (! $config->quickbooksConnected()) {
            throw new RuntimeException('QuickBooks is not connected.');
        }

        $expires = $config->qbo_token_expires_at;
        $needsRefresh = $config->qbo_access_token_enc === null
            || $config->qbo_access_token_enc === ''
            || $expires === null
            || now()->greaterThanOrEqualTo($expires->copy()->subSeconds(120));

        if ($needsRefresh) {
            $this->refreshAccessToken($config);
            $config->refresh();
        }
    }

    public function refreshAccessTokenIfExpiredForIntegration(Integration $integration): void
    {
        if (! $integration->access_token || ! $integration->refresh_token) {
            throw new RuntimeException('QuickBooks integration is not connected.');
        }

        $expires = $integration->token_expires_at;
        $needsRefresh = $expires === null
            || now()->greaterThanOrEqualTo($expires->copy()->subSeconds(120));

        if ($needsRefresh) {
            $this->refreshAccessTokenForIntegration($integration);
            $integration->refresh();
        }
    }

    private function guardTokenResponse(Response $response, string $grantType): void
    {
        if ($response->failed()) {
            Log::error('QuickBooks token request failed', [
                'grant_type' => $grantType,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('QuickBooks token request failed.');
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['access_token']) || empty($data['refresh_token'])) {
            Log::error('QuickBooks token response missing fields', [
                'grant_type' => $grantType,
                'body' => $response->body(),
            ]);

            throw new RuntimeException('QuickBooks token response was incomplete.');
        }
    }
}
