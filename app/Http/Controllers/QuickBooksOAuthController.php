<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Payment\Models\PaymentConfiguration;
use App\Models\AccountSettings;
use App\Models\Tenant;
use App\Services\Payments\QuickBooksOAuthService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central QuickBooks Online OAuth callback (one redirect URI for all tenants, per Intuit registration).
 * `state` is a one-time handoff id stored in the central database that maps back to the originating
 * tenant + tenant user profile.
 */
class QuickBooksOAuthController extends Controller
{
    public function callback(Request $request, QuickBooksOAuthService $oauth)
    {
        if ($request->filled('error')) {
            $message = $request->input('error_description')
                ?: $request->input('error', 'QuickBooks authorization was cancelled or denied.');

            return response($message, 400)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $code = (string) $request->input('code', '');
        $state = (string) $request->input('state', '');
        $realmId = (string) $request->input('realmId', '');

        if ($code === '' || $state === '' || $realmId === '') {
            return response('Missing authorization code, state, or realmId.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $central = (string) config('tenancy.database.central_connection');

        $row = DB::connection($central)->table('quickbooks_oauth_handoffs')->where('id', $state)->first();

        if (! $row || now()->gt(Carbon::parse($row->expires_at))) {
            return response(
                'This QuickBooks authorization link is invalid or has expired. Open Account → Payments and click Connect again.',
                410
            )->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        DB::connection($central)->table('quickbooks_oauth_handoffs')->where('id', $state)->delete();

        $tenant = Tenant::query()->find($row->tenant_id);
        if (! $tenant) {
            Log::error('QuickBooks OAuth: tenant not found for handoff', ['tenant_id' => $row->tenant_id]);

            return response('Workspace not found.', 404)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $domain = $tenant->domains()->orderBy('id')->value('domain');
        if (! $domain) {
            Log::error('QuickBooks OAuth: tenant has no domain', ['tenant_id' => $tenant->id]);

            return response('This workspace has no web address configured. Contact support.', 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $redirectUri = (string) config('services.quickbooks.redirect_uri');
        if ($redirectUri === '') {
            Log::error('QuickBooks OAuth: redirect URI is empty');

            return response('Server is not configured for QuickBooks OAuth.', 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        try {
            tenancy()->initialize($tenant);

            try {
                if (PaymentConfiguration::stripeConnectClaimed(AccountSettings::getCurrent())) {
                    return redirect()->away($this->tenantPaymentsUrl($domain, [
                        'qbo_error' => 'stripe_active',
                    ]));
                }

                $tokens = $oauth->exchangeCode($code, $redirectUri);

                $config = PaymentConfiguration::forQuickbooks(AccountSettings::getCurrent());
                $companyInfo = $oauth->fetchCompanyInfo($tokens['access_token'], $realmId);
                $oauth->persistConnection($config, $realmId, $tokens, $companyInfo);

                return redirect()->away($this->tenantPaymentsUrl($domain, [
                    'qbo_connected' => '1',
                ]));
            } finally {
                tenancy()->end();
            }
        } catch (\Throwable $e) {
            Log::error('QuickBooks central OAuth callback error', [
                'exception' => $e->getMessage(),
                'redirect_uri' => $redirectUri,
            ]);

            return redirect()->away($this->tenantPaymentsUrl($domain, [
                'qbo_error' => 'token',
            ]));
        }
    }

    protected function tenantPaymentsUrl(string $domain, array $query): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$domain.'/account/payments/quickbooks?'.http_build_query($query);
    }
}
