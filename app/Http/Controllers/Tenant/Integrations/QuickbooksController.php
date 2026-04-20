<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integrations;

use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationType;
use App\Http\Controllers\Controller;
use App\Jobs\PullContactsFromQuickBooks;
use App\Services\Payments\QuickBooksOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * QuickBooks Online under Integrations (OAuth + customer import). Central callback:
 * {@see \App\Http\Controllers\QuickBooksOAuthController}.
 */
class QuickbooksController extends Controller
{
    public function __construct(protected QuickBooksOAuthService $oauth) {}

    public function show(Request $request): Response
    {
        $oauthNotice = null;
        if ($request->boolean('qbo_connected')) {
            $oauthNotice = [
                'type' => 'success',
                'message' => 'QuickBooks Online connected successfully.',
            ];
        } elseif ($request->filled('qbo_error')) {
            $oauthNotice = [
                'type' => 'error',
                'message' => match ($request->query('qbo_error')) {
                    'token' => 'QuickBooks did not return a token. Confirm QUICKBOOKS_REDIRECT_URI matches the redirect URL registered in your Intuit app exactly, then try again.',
                    default => 'QuickBooks connection failed. Please try again.',
                },
            ];
        }

        $profile = current_tenant_profile();
        $centralUser = auth()->user();

        $integrationMeta = [
            'id' => IntegrationType::QuickBooks->value,
            'type' => IntegrationType::QuickBooks->slug(),
            'name' => IntegrationType::QuickBooks->label(),
            'description' => IntegrationType::QuickBooks->description(),
            'icon' => IntegrationType::QuickBooks->icon(),
            'category' => IntegrationType::QuickBooks->category(),
            'requires_oauth' => IntegrationType::QuickBooks->requiresOAuth(),
        ];

        $currentIntegration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        $meta = $currentIntegration?->metadata ?? [];

        $breadcrumbs = [
            'current' => $integrationMeta['name'],
            'links' => [
                ['url' => route('dashboard'), 'name' => 'Dashboard'],
                ['url' => route('integrations'), 'name' => 'Integrations'],
            ],
        ];

        return Inertia::render('Tenant/Integrations/Quickbooks', [
            'oauthNotice' => $oauthNotice,
            'breadcrumbs' => $breadcrumbs,
            'centralUser' => $centralUser ? [
                'id' => $centralUser->id,
                'name' => $centralUser->name ?? trim(($centralUser->first_name ?? '').' '.($centralUser->last_name ?? '')),
                'email' => $centralUser->email,
            ] : null,
            'tenantProfile' => $profile ? [
                'id' => $profile->id,
                'display_name' => $profile->display_name ?? $profile->email,
            ] : null,
            'integration' => $integrationMeta,
            'hasQuickbooksToken' => (bool) $currentIntegration?->access_token,
            'currentIntegration' => $currentIntegration ? [
                'id' => $currentIntegration->id,
                'active' => (bool) $currentIntegration->active,
                'last_synced_at' => $currentIntegration->last_synced_at?->toIso8601String(),
                'sync_status' => $currentIntegration->sync_status?->value,
            ] : null,
            'quickbooks' => [
                'realm_id' => $currentIntegration?->external_id,
                'environment' => $meta['qbo_environment'] ?? config('services.quickbooks.environment', 'sandbox'),
                'company_name' => $meta['qbo_company_name'] ?? null,
                'legal_name' => $meta['qbo_legal_name'] ?? null,
                'country' => $meta['qbo_country'] ?? null,
                'email' => $meta['qbo_email'] ?? null,
                'connected_at' => $meta['qbo_connected_at'] ?? null,
                'token_expires_at' => $currentIntegration?->token_expires_at?->toIso8601String(),
                'refresh_token_expires_at' => $meta['qbo_refresh_token_expires_at'] ?? null,
            ],
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $profile = current_tenant_profile();
        if (! $profile) {
            return redirect()->route('integrations')->withErrors('Could not resolve your user profile for this workspace.');
        }

        $tenant = tenant();
        if (! $tenant) {
            return redirect()->route('integrations')->withErrors('Could not resolve the current workspace.');
        }

        $redirectUri = (string) config('services.quickbooks.redirect_uri');
        if ($redirectUri === '' || ! filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            Log::error('QuickBooks OAuth: invalid or missing services.quickbooks.redirect_uri');

            return redirect()->route('integrations')->withErrors(
                'QuickBooks redirect URL is not configured. Set QUICKBOOKS_REDIRECT_URI to your central callback URL (see config/services.php).'
            );
        }

        if (! config('services.quickbooks.client_id') || ! config('services.quickbooks.client_secret')) {
            return redirect()->route('integrations')->withErrors(
                'QuickBooks client credentials are missing. Set QUICKBOOKS_CLIENT_ID and QUICKBOOKS_CLIENT_SECRET.'
            );
        }

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

    public function destroy(): RedirectResponse
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $integration) {
            return redirect()->route('quickbooks')->withErrors('No QuickBooks integration found.');
        }

        if ($integration->refresh_token) {
            $this->oauth->revoke($integration->refresh_token);
        }

        $integration->delete();

        return redirect()->route('integrations')->with('success', 'QuickBooks Online has been disconnected.');
    }

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

        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $integration?->access_token || ! $integration->refresh_token) {
            return response()->json([
                'error' => 'QuickBooks is not connected. Open Integrations → QuickBooks Online and connect first.',
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
