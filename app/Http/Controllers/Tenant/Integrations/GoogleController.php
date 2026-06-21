<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integrations;

use App\Domain\AssetUnit\Support\AssetUnitGoogleSheetSyncService;
use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\GoogleIntegrationSettings;
use App\Enums\Integration\IntegrationType;
use App\Http\Controllers\Controller;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class GoogleController extends Controller
{
    public function __construct(
        private readonly GoogleOAuthService $oauth,
    ) {}

    public function show(Request $request): Response
    {
        $oauthNotice = null;
        if ($request->boolean('google_connected')) {
            $oauthNotice = [
                'type' => 'success',
                'message' => 'Google account connected successfully.',
            ];
        } elseif ($request->filled('google_error')) {
            $oauthNotice = [
                'type' => 'error',
                'message' => match ($request->query('google_error')) {
                    'denied' => 'Google authorization was cancelled or denied.',
                    'token' => 'Google did not return a token. Confirm GOOGLE_REDIRECT_URI matches your Google Cloud OAuth client.',
                    default => 'Google connection failed. Please try again.',
                },
            ];
        }

        $integration = Integration::query()
            ->where('integration_type', IntegrationType::Google)
            ->first();

        $sheetSettings = GoogleIntegrationSettings::from($integration);

        return Inertia::render('Tenant/Integrations/Google', [
            'oauthNotice' => $oauthNotice,
            'breadcrumbs' => [
                'current' => IntegrationType::Google->label(),
                'links' => [
                    ['url' => route('dashboard'), 'name' => 'Dashboard'],
                    ['url' => route('integrations'), 'name' => 'Integrations'],
                ],
            ],
            'integration' => [
                'id' => IntegrationType::Google->value,
                'type' => IntegrationType::Google->slug(),
                'name' => IntegrationType::Google->label(),
                'description' => IntegrationType::Google->description(),
            ],
            'isConnected' => $this->oauth->hasCredentials(),
            'googleEmail' => $integration?->metadata['google_email'] ?? null,
            'sheetSettings' => $sheetSettings->toArray(),
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

        $redirectUri = (string) config('services.google.redirect_uri');
        if ($redirectUri === '' || ! filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            return redirect()->route('integrations')->withErrors(
                'Google redirect URL is not configured. Set GOOGLE_REDIRECT_URI to your central callback URL.'
            );
        }

        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('integrations')->withErrors(
                'Google client credentials are missing. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.'
            );
        }

        $handoffId = (string) Str::uuid();
        $central = (string) config('tenancy.database.central_connection');

        DB::connection($central)->table('google_oauth_handoffs')->insert([
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
            ->where('integration_type', IntegrationType::Google)
            ->first();

        if ($integration) {
            $this->oauth->revokeToken($integration->access_token);
            $integration->delete();
        }

        return redirect()->route('google')->with('success', 'Google disconnected.');
    }

    public function pushSheet(Request $request): JsonResponse
    {
        try {
            $result = app(AssetUnitGoogleSheetSyncService::class)->push();

            return response()->json($result);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function pullSheet(Request $request): JsonResponse
    {
        try {
            $result = app(AssetUnitGoogleSheetSyncService::class)->pull();

            return response()->json($result);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recreateSheet(Request $request): JsonResponse
    {
        try {
            $result = app(AssetUnitGoogleSheetSyncService::class)->recreate();

            return response()->json($result);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
