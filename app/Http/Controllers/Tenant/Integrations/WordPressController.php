<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integrations;

use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\WordPressIntegrationSettings;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use App\Http\Controllers\Controller;
use App\Services\Integrations\WordPressApiService;
use App\Services\Integrations\WordPressPluginZipBuilder;
use App\Support\TenantAbsoluteUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WordPressController extends Controller
{
    public function __construct(
        private readonly WordPressApiService $wordpress,
        private readonly WordPressPluginZipBuilder $pluginZip,
    ) {}

    public function show(Request $request): Response
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::WordPress)
            ->first();

        $settings = WordPressIntegrationSettings::from($integration);

        return Inertia::render('Tenant/Integrations/WordPress', [
            'breadcrumbs' => [
                'current' => IntegrationType::WordPress->label(),
                'links' => [
                    ['url' => route('dashboard'), 'name' => 'Dashboard'],
                    ['url' => route('integrations'), 'name' => 'Integrations'],
                ],
            ],
            'integration' => [
                'id' => IntegrationType::WordPress->value,
                'type' => IntegrationType::WordPress->slug(),
                'name' => IntegrationType::WordPress->label(),
                'description' => IntegrationType::WordPress->description(),
            ],
            'isConnected' => $settings->isConnected(),
            'wordpressSettings' => $settings->toArray(),
            'tenantDomain' => parse_url((string) TenantAbsoluteUrl::root(), PHP_URL_HOST),
            'helmfulApiKey' => $request->session()->pull('wordpress_helmful_api_key'),
            'pluginDownloadUrl' => route('wordpress.download-plugin'),
            'pluginVersion' => $this->pluginZip->version(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $existing = Integration::query()
            ->where('integration_type', IntegrationType::WordPress)
            ->first();

        $validated = $request->validate([
            'wordpress_url' => ['required', 'url', 'max:255'],
            'wordpress_api_key' => [$existing ? 'nullable' : 'required', 'string', 'min:16', 'max:255'],
            'auto_push_enabled' => ['sometimes', 'boolean'],
        ]);

        $url = rtrim($validated['wordpress_url'], '/');
        $userId = current_tenant_profile()?->getKey();

        $integration = $existing ?? new Integration([
            'integration_type' => IntegrationType::WordPress,
        ]);

        $settings = $integration->settings ?? [];
        $helmfulSyncToken = null;
        if (! isset($settings['helmful_api_key_hash'])) {
            $helmfulKey = Str::random(64);
            $settings['helmful_api_key_hash'] = WordPressIntegrationSettings::hashApiKey($helmfulKey);
            $request->session()->flash('wordpress_helmful_api_key', $helmfulKey);
            $helmfulSyncToken = $helmfulKey;
        }

        $settings['wordpress_url'] = $url;
        $settings['auto_push_enabled'] = $request->boolean('auto_push_enabled', true);

        $integration->fill([
            'user_id' => $userId,
            'name' => IntegrationType::WordPress->label(),
            'active' => true,
            'settings' => $settings,
            'sync_status' => IntegrationSyncStatus::Pending,
        ]);

        $secrets = ['external_id' => hash('sha256', $url)];
        if (filled($validated['wordpress_api_key'] ?? null)) {
            $secrets['access_token'] = $validated['wordpress_api_key'];
        }
        if ($helmfulSyncToken !== null) {
            $secrets['sync_token'] = $helmfulSyncToken;
        }
        $integration->forceFill($secrets);

        $integration->save();

        $syncNotice = null;
        if ($integration->access_token) {
            $sync = $this->wordpress->syncWordPressApiKeyToSite();
            if (! ($sync['success'] ?? false)) {
                $syncNotice = $sync['message'] ?? 'Could not register the WordPress API key on your site.';
            }
        }

        return redirect()->route('wordpress')->with([
            'success' => 'WordPress integration saved.',
            'error' => $syncNotice,
        ]);
    }

    public function destroy(): RedirectResponse
    {
        Integration::query()
            ->where('integration_type', IntegrationType::WordPress)
            ->delete();

        return redirect()->route('wordpress')->with('success', 'WordPress disconnected.');
    }

    public function regenerateKey(Request $request): RedirectResponse
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::WordPress)
            ->first();

        $isNew = $integration === null;

        if ($isNew) {
            $integration = new Integration([
                'integration_type' => IntegrationType::WordPress,
            ]);
        }

        $helmfulKey = Str::random(64);
        $settings = $integration->settings ?? [];
        $settings['helmful_api_key_hash'] = WordPressIntegrationSettings::hashApiKey($helmfulKey);

        $integration->fill([
            'user_id' => current_tenant_profile()?->getKey(),
            'name' => IntegrationType::WordPress->label(),
            'active' => true,
            'settings' => $settings,
            'sync_status' => IntegrationSyncStatus::Pending,
        ]);

        if (! filled($integration->external_id)) {
            $integration->forceFill([
                'external_id' => 'wordpress:'.(tenant()?->getTenantKey() ?? 'default'),
            ]);
        }

        $integration->forceFill([
            'sync_token' => $helmfulKey,
        ]);

        $integration->save();

        return redirect()->route('wordpress')->with([
            'success' => $isNew
                ? 'Integration key generated. Copy it into WordPress under Settings → Helmful Sync.'
                : 'Integration key regenerated. Copy the new key into WordPress before the old key stops working.',
            'wordpress_helmful_api_key' => $helmfulKey,
        ]);
    }

    public function testConnection(): JsonResponse
    {
        $sync = $this->wordpress->syncWordPressApiKeyToSite();
        if (! ($sync['success'] ?? false) && ($sync['blocking'] ?? false)) {
            return response()->json($sync, 422);
        }

        $result = $this->wordpress->ping();
        if (! ($result['success'] ?? false) && ! ($sync['success'] ?? false) && isset($sync['message'])) {
            $result['message'] = trim(($sync['message'] ?? '').' '.($result['message'] ?? ''));
        }

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function pushAll(): JsonResponse
    {
        try {
            $result = $this->wordpress->pushAll();

            return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function downloadPlugin(): BinaryFileResponse
    {
        $zipPath = $this->pluginZip->build();

        return response()->download(
            $zipPath,
            WordPressPluginZipBuilder::ZIP_FILENAME,
            ['Content-Type' => 'application/zip'],
        )->deleteFileAfterSend(true);
    }
}
