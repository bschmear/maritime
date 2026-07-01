<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integrations;

use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\EasyPostIntegrationSettings;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use App\Http\Controllers\Controller;
use App\Services\Integrations\EasyPostService;
use App\Support\Tenant\TenantNavigationCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EasyPostController extends Controller
{
    public function __construct(
        private readonly EasyPostService $easyPost,
    ) {}

    public function show(): Response
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::EasyPost)
            ->first();

        $settings = EasyPostIntegrationSettings::from($integration);

        return Inertia::render('Tenant/Integrations/EasyPost', [
            'breadcrumbs' => [
                'current' => IntegrationType::EasyPost->label(),
                'links' => [
                    ['url' => route('dashboard'), 'name' => 'Dashboard'],
                    ['url' => route('integrations'), 'name' => 'Integrations'],
                ],
            ],
            'integration' => [
                'id' => IntegrationType::EasyPost->value,
                'type' => IntegrationType::EasyPost->slug(),
                'name' => IntegrationType::EasyPost->label(),
                'description' => IntegrationType::EasyPost->description(),
            ],
            'isConnected' => $settings->isConnected(),
            'easypostSettings' => $settings->toArray(),
            'currentIntegration' => $integration ? [
                'id' => $integration->id,
                'active' => (bool) $integration->active,
                'last_synced_at' => $integration->last_synced_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $existing = Integration::query()
            ->where('integration_type', IntegrationType::EasyPost)
            ->first();

        $validated = $request->validate([
            'api_key' => [$existing && $existing->access_token ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'test_mode' => ['sometimes', 'boolean'],
        ]);

        $apiKey = $validated['api_key'] ?? $existing?->access_token;
        if (! filled($apiKey)) {
            return back()->withErrors(['api_key' => 'EasyPost API key is required.']);
        }

        $test = $this->easyPost->testConnection($apiKey);
        if (! ($test['success'] ?? false)) {
            return back()->withErrors(['api_key' => $test['message'] ?? 'Could not connect to EasyPost.']);
        }

        $integration = $existing ?? new Integration([
            'integration_type' => IntegrationType::EasyPost,
        ]);

        $settingsPayload = [
            'test_mode' => $request->boolean('test_mode', true),
        ];

        $integration->fill([
            'user_id' => current_tenant_profile()?->getKey(),
            'name' => IntegrationType::EasyPost->label(),
            'active' => true,
            'settings' => EasyPostIntegrationSettings::from($integration)->mergeIntoIntegrationSettings($settingsPayload),
            'sync_status' => IntegrationSyncStatus::Success,
        ]);

        $integration->forceFill([
            'access_token' => $apiKey,
            'external_id' => $test['user_id'] ?? hash('sha256', $apiKey),
        ]);

        $integration->save();

        TenantNavigationCache::bumpVersion();

        return redirect()->route('easypost')->with('success', 'EasyPost settings saved.');
    }

    public function destroy(): RedirectResponse
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::EasyPost)
            ->first();

        if ($integration) {
            $integration->update(['active' => false]);
        }

        TenantNavigationCache::bumpVersion();

        return redirect()->route('easypost')->with('success', 'EasyPost has been disabled. Turn it back on anytime without re-entering your API key.');
    }

    public function activate(): RedirectResponse
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::EasyPost)
            ->first();

        $settings = EasyPostIntegrationSettings::from($integration);

        if (! $settings->hasApiKey()) {
            return redirect()->route('easypost')->withErrors('Save your EasyPost API key before enabling the integration.');
        }

        $integration?->update(['active' => true]);

        TenantNavigationCache::bumpVersion();

        return redirect()->route('easypost')->with('success', 'EasyPost integration enabled.');
    }

    public function testConnection(): JsonResponse
    {
        $result = $this->easyPost->testConnection();

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }
}
