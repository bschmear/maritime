<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Google\GoogleOAuthService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central Google OAuth callback (one redirect URI for all tenants).
 */
class GoogleOAuthController extends Controller
{
    public function callback(Request $request, GoogleOAuthService $oauth)
    {
        if ($request->filled('error')) {
            $message = $request->input('error_description')
                ?: $request->input('error', 'Google authorization was cancelled or denied.');

            return $this->redirectToTenantWithError(null, 'denied', $message);
        }

        $code = (string) $request->input('code', '');
        $state = (string) $request->input('state', '');

        if ($code === '' || $state === '') {
            return response('Missing authorization code or state.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $central = (string) config('tenancy.database.central_connection');
        $row = DB::connection($central)->table('google_oauth_handoffs')->where('id', $state)->first();

        if (! $row || now()->gt(Carbon::parse($row->expires_at))) {
            return response(
                'This Google authorization link is invalid or has expired. Open Integrations → Google and click Connect again.',
                410
            )->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        DB::connection($central)->table('google_oauth_handoffs')->where('id', $state)->delete();

        $tenant = Tenant::query()->find($row->tenant_id);
        if (! $tenant) {
            Log::error('Google OAuth: tenant not found for handoff', ['tenant_id' => $row->tenant_id]);

            return response('Workspace not found.', 404)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $redirectUri = (string) config('services.google.redirect_uri');
        if ($redirectUri === '') {
            return response('Server is not configured for Google OAuth.', 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        try {
            tenancy()->initialize($tenant);

            $tokens = $oauth->exchangeCode($code, $redirectUri);
            $email = $oauth->fetchUserEmail($tokens['access_token']);

            $oauth->persistConnection(
                (int) $row->tenant_user_profile_id,
                $tokens,
                email: $email,
            );
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return $this->redirectToTenantWithError($tenant, 'token');
        }

        $domain = $tenant->domains()->orderBy('id')->value('domain');

        return redirect()->away('https://'.$domain.route('google', [], false).'?google_connected=1');
    }

    private function redirectToTenantWithError(?Tenant $tenant, string $code, ?string $message = null)
    {
        if ($tenant === null) {
            return response($message ?? 'Google connection failed.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $domain = $tenant->domains()->orderBy('id')->value('domain');

        return redirect()->away('https://'.$domain.route('google', [], false).'?google_error='.$code);
    }
}
