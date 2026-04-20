<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Payment\Models\PaymentConfiguration;
use App\Http\Controllers\Controller;
use App\Jobs\PullContactsFromQuickBooks;
use App\Models\AccountSettings;
use App\Services\Payments\QuickBooksOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Tenant-side bookends for the central {@see \App\Http\Controllers\QuickBooksOAuthController}.
 * `connect()` mints a handoff and redirects to Intuit; the central callback writes tokens back here.
 */
class QuickBooksController extends Controller
{
    public function __construct(protected QuickBooksOAuthService $oauth) {}

    public function connect(Request $request): RedirectResponse
    {
        $profile = current_tenant_profile();
        if (! $profile) {
            return redirect()->route('account.payments.quickbooks')
                ->with('error', 'Could not resolve your user profile for this workspace.');
        }

        $tenant = tenant();
        if (! $tenant) {
            return redirect()->route('account.payments.quickbooks')
                ->with('error', 'Could not resolve the current workspace.');
        }

        $redirectUri = (string) config('services.quickbooks.redirect_uri');
        if ($redirectUri === '' || ! filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            Log::error('QuickBooks OAuth: invalid or missing services.quickbooks.redirect_uri');

            return redirect()->route('account.payments.quickbooks')->with(
                'error',
                'QuickBooks redirect URL is not configured. Set QUICKBOOKS_REDIRECT_URI to your central callback URL (see config/services.php).'
            );
        }

        if (! config('services.quickbooks.client_id') || ! config('services.quickbooks.client_secret')) {
            return redirect()->route('account.payments.quickbooks')->with(
                'error',
                'QuickBooks client credentials are missing. Set QUICKBOOKS_CLIENT_ID and QUICKBOOKS_CLIENT_SECRET.'
            );
        }

        if (PaymentConfiguration::stripeConnectClaimed(AccountSettings::getCurrent())) {
            return redirect()->route('account.payments.quickbooks')->with(
                'error',
                'This workspace already uses Stripe for payments. Disconnect Stripe on the Stripe page before connecting QuickBooks Online.'
            );
        }

        // Pre-create the row so disconnect/UI work even if the user bails mid-flow.
        PaymentConfiguration::forQuickbooks(AccountSettings::getCurrent());

        $handoffId = (string) Str::uuid();
        $central = (string) config('tenancy.database.central_connection');

        DB::connection($central)->table('quickbooks_oauth_handoffs')->insert([
            'id' => $handoffId,
            'tenant_id' => $tenant->getTenantKey(),
            'tenant_user_profile_id' => $profile->getKey(),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->away($this->oauth->authorizeUrl($handoffId, $redirectUri));
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $config = PaymentConfiguration::forQuickbooks(AccountSettings::getCurrent());

        if ($config->qbo_refresh_token_enc) {
            // Revoke the refresh token at Intuit (best effort) before clearing local state.
            $this->oauth->revoke($config->qbo_refresh_token_enc);
        }

        $config->update([
            'qbo_realm_id' => null,
            'qbo_access_token_enc' => null,
            'qbo_refresh_token_enc' => null,
            'qbo_token_expires_at' => null,
            'meta' => array_merge($config->meta ?? [], [
                'qbo_disconnected_at' => now()->toIso8601String(),
                'qbo_company_name' => null,
                'qbo_legal_name' => null,
                'qbo_country' => null,
                'qbo_email' => null,
                'qbo_refresh_token_expires_at' => null,
            ]),
        ]);

        return redirect()->route('account.payments.quickbooks')
            ->with('success', 'QuickBooks Online disconnected.');
    }

    /**
     * Queue a background import of QuickBooks Online customers into contacts or leads (AJAX/JSON).
     */
    public function importCustomers(Request $request): JsonResponse
    {
        if (! $request->ajax() && ! $request->wantsJson()) {
            abort(405, 'This endpoint must be accessed via AJAX or JSON.');
        }

        $request->validate([
            'type' => ['required', 'string', Rule::in(['contact', 'lead'])],
        ]);

        $profile = current_tenant_profile();
        if (! $profile) {
            return response()->json(['error' => 'Tenant user profile not found.'], 403);
        }

        $qb = PaymentConfiguration::forQuickbooks();
        if (! $qb->quickbooksConnected()) {
            return response()->json([
                'error' => 'QuickBooks is not connected. Connect QuickBooks in Account → Payments first.',
            ], 422);
        }

        PullContactsFromQuickBooks::dispatch(
            (int) $profile->getKey(),
            $request->input('type'),
        );

        return response()->json([
            'message' => 'QuickBooks customer import queued. Records may take a few minutes to appear.',
        ]);
    }
}
