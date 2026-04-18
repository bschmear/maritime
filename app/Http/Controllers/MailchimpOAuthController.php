<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationType;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Single Mailchimp OAuth redirect URI for all tenants (registered in Mailchimp as MAILCHIMP_REDIRECT_URI).
 * State is a one-time handoff id stored in the central database.
 */
class MailchimpOAuthController extends Controller
{
    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            $message = $request->input('error_description')
                ?: $request->input('error', 'Mailchimp authorization was cancelled or denied.');

            return response($message, 400)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $code = $request->input('code');
        $state = (string) $request->input('state', '');

        if (! $code || $state === '') {
            return response('Missing authorization code or state.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $central = (string) config('tenancy.database.central_connection');

        $row = DB::connection($central)->table('mailchimp_oauth_handoffs')->where('id', $state)->first();

        if (! $row || now()->gt(Carbon::parse($row->expires_at))) {
            return response(
                'This Mailchimp authorization link is invalid or has expired. Close this tab, open your workspace, and click Connect again.',
                410
            )->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        DB::connection($central)->table('mailchimp_oauth_handoffs')->where('id', $state)->delete();

        $tenant = Tenant::query()->find($row->tenant_id);
        if (! $tenant) {
            Log::error('Mailchimp OAuth: tenant not found for handoff', ['tenant_id' => $row->tenant_id]);

            return response('Workspace not found.', 404)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $domain = $tenant->domains()->orderBy('id')->value('domain');
        if (! $domain) {
            Log::error('Mailchimp OAuth: tenant has no domain', ['tenant_id' => $tenant->id]);

            return response('This workspace has no web address configured. Contact support.', 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $redirectUri = (string) config('services.mailchimp.redirect_uri');
        if ($redirectUri === '') {
            Log::error('Mailchimp OAuth: redirect URI is empty');

            return response('Server is not configured for Mailchimp OAuth.', 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        try {
            $response = Http::asForm()->post('https://login.mailchimp.com/oauth2/token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.mailchimp.client_id'),
                'client_secret' => config('services.mailchimp.client_secret'),
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

            if ($response->failed()) {
                Log::error('Mailchimp token request failed', [
                    'response' => $response->body(),
                    'redirect_uri' => $redirectUri,
                ]);

                return redirect()->away($this->tenantMailchimpUrl($domain, [
                    'mailchimp_error' => 'token',
                ]));
            }

            $data = $response->json();

            if (! isset($data['access_token']) || empty($data['access_token'])) {
                Log::error('Mailchimp access token missing', ['response' => $data]);

                return redirect()->away($this->tenantMailchimpUrl($domain, [
                    'mailchimp_error' => 'token',
                ]));
            }

            $metadataResponse = Http::withToken($data['access_token'])
                ->get('https://login.mailchimp.com/oauth2/metadata');

            if ($metadataResponse->failed()) {
                Log::error('Mailchimp metadata request failed', ['response' => $metadataResponse->body()]);

                return redirect()->away($this->tenantMailchimpUrl($domain, [
                    'mailchimp_error' => 'metadata',
                ]));
            }

            $metadata = $metadataResponse->json();
            $externalId = (string) ($metadata['user_id'] ?? 'mailchimp');

            tenancy()->initialize($tenant);

            try {
                Integration::query()->updateOrCreate(
                    [
                        'integration_type' => (string) IntegrationType::MailChimp->value,
                        'external_id' => $externalId,
                    ],
                    [
                        'user_id' => (int) $row->tenant_user_profile_id,
                        'name' => IntegrationType::MailChimp->label(),
                        'access_token' => $data['access_token'],
                        'refresh_token' => $data['refresh_token'] ?? '',
                        'token_expires_at' => isset($data['expires_in']) && $data['expires_in'] > 0
                            ? now()->addSeconds((int) $data['expires_in'])
                            : null,
                        'metadata' => $metadata,
                        'active' => true,
                    ]
                );
            } finally {
                tenancy()->end();
            }

            return redirect()->away($this->tenantMailchimpUrl($domain, [
                'mailchimp_connected' => '1',
            ]));
        } catch (\Throwable $e) {
            Log::error('Mailchimp central OAuth callback error', ['exception' => $e]);

            return redirect()->away($this->tenantMailchimpUrl($domain, [
                'mailchimp_error' => 'unexpected',
            ]));
        }
    }

    protected function tenantMailchimpUrl(string $domain, array $query): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $path = '/integrations/mailchimp';

        return $scheme.'://'.$domain.$path.'?'.http_build_query($query);
    }
}
