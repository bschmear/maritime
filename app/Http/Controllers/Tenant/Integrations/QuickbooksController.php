<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integrations;

use App\Domain\Integration\Models\Integration;
use App\Domain\Integration\Support\QuickBooksSettings;
use App\Enums\Integration\IntegrationSyncStatus;
use App\Enums\Integration\IntegrationType;
use App\Enums\ServiceItem\BillingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\QuickBooksOAuthController;
use App\Jobs\PullBillPaymentsFromQuickBooks;
use App\Jobs\PullBillsFromQuickBooks;
use App\Jobs\PullChartOfAccountsFromQuickBooks;
use App\Jobs\PullContactsFromQuickBooks;
use App\Jobs\PullServiceItemsFromQuickBooks;
use App\Jobs\PullVendorsFromQuickBooks;
use App\Services\Payments\QuickBooksAccountingService;
use App\Services\Payments\QuickBooksOAuthService;
use App\Support\QuickBooks\QuickBooksImportDateRange;
use App\Support\QuickBooks\QuickBooksImportStatus;
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
 * {@see QuickBooksOAuthController}.
 */
class QuickbooksController extends Controller
{
    public function __construct(
        protected QuickBooksOAuthService $oauth,
        protected QuickBooksAccountingService $accounting,
    ) {}

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
        $syncSettings = QuickBooksSettings::from($currentIntegration);

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
            'hasQuickbooksToken' => $this->accounting->hasCredentials(),
            'isQuickbooksEnabled' => $this->accounting->isConnected(),
            'currentIntegration' => $currentIntegration ? [
                'id' => $currentIntegration->id,
                'active' => (bool) $currentIntegration->active,
                'last_synced_at' => $currentIntegration->last_synced_at?->toIso8601String(),
                'sync_status' => $currentIntegration->sync_status?->value,
                'sync_error_message' => $currentIntegration->sync_error_message,
                'sync_operation' => $meta['sync_operation'] ?? null,
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
            'syncSettings' => [
                'sync_contacts' => $syncSettings->syncContacts,
                'sync_invoices' => $syncSettings->syncInvoices,
                'sync_payments' => $syncSettings->syncPayments,
                'sync_bills' => $syncSettings->syncBills,
                'sync_bill_payments' => $syncSettings->syncBillPayments,
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $this->accounting->hasCredentials()) {
            return redirect()->route('quickbooks')->withErrors('Connect QuickBooks Online before changing sync options.');
        }

        $validated = $request->validate([
            'sync_contacts' => ['required', 'boolean'],
            'sync_invoices' => ['required', 'boolean'],
            'sync_payments' => ['required', 'boolean'],
            'sync_bills' => ['required', 'boolean'],
            'sync_bill_payments' => ['required', 'boolean'],
        ]);

        QuickBooksSettings::from($integration)->mergeIntoIntegrationSettings($integration, $validated);

        return redirect()->route('quickbooks')->with('success', 'QuickBooks sync options saved.');
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

        $integration->update(['active' => false]);

        return redirect()->route('quickbooks')->with('success', 'QuickBooks Online has been disabled. Turn it back on anytime without reconnecting.');
    }

    public function activate(): RedirectResponse
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $integration || ! $this->accounting->hasCredentials()) {
            return redirect()->route('quickbooks')->withErrors('Connect QuickBooks Online before enabling the integration.');
        }

        $integration->update(['active' => true]);

        return redirect()->route('quickbooks')->with('success', 'QuickBooks Online integration enabled.');
    }

    public function importCustomers(Request $request): JsonResponse
    {
        if (! $request->expectsJson()) {
            abort(405, 'This endpoint must be accessed via AJAX or JSON.');
        }

        $request->validate([
            'type' => ['required', 'string', Rule::in(['contact', 'customer', 'lead'])],
        ]);

        $profile = current_tenant_profile();
        if (! $profile) {
            return response()->json(['error' => 'Tenant user profile not found.'], 403);
        }

        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $this->accounting->isConnected()) {
            return response()->json([
                'error' => 'QuickBooks integration is disabled or not connected. Enable it under Integrations → QuickBooks.',
            ], 422);
        }

        $profileId = (int) $profile->getKey();
        $importType = $request->input('type');

        $queueConnection = config('queue.default');
        $queueDbConnection = config('queue.connections.database.connection');

        Log::info('QuickBooks customer import: dispatch requested', [
            'profile_id' => $profileId,
            'import_type' => $importType,
            'tenant_id' => tenancy()->initialized ? tenancy()->tenant?->getTenantKey() : null,
            'tenancy_initialized' => tenancy()->initialized,
            'queue_connection' => $queueConnection,
            'queue_db_connection' => $queueDbConnection,
        ]);

        try {
            PullContactsFromQuickBooks::dispatch($profileId, $importType);
        } catch (\Throwable $e) {
            Log::error('QuickBooks customer import: dispatch failed', [
                'profile_id' => $profileId,
                'import_type' => $importType,
                'tenant_id' => tenancy()->initialized ? tenancy()->tenant?->getTenantKey() : null,
                'queue_connection' => $queueConnection,
                'queue_db_connection' => $queueDbConnection,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Could not queue the import job. Check that the central jobs table exists and queue workers are running.',
            ], 500);
        }

        Log::info('QuickBooks customer import: job queued', [
            'profile_id' => $profileId,
            'import_type' => $importType,
            'tenant_id' => tenancy()->initialized ? tenancy()->tenant?->getTenantKey() : null,
            'queue_connection' => $queueConnection,
            'queue_db_connection' => $queueDbConnection,
        ]);

        return response()->json([
            'message' => 'QuickBooks customer import queued. Records may take a few minutes to appear.',
        ]);
    }

    public function importServiceItems(Request $request): JsonResponse
    {
        if (! $request->expectsJson()) {
            abort(405, 'This endpoint must be accessed via AJAX or JSON.');
        }

        $validated = $request->validate([
            'billing_type' => ['required', 'integer', Rule::in([
                BillingType::Hourly->value,
                BillingType::Flat->value,
            ])],
        ]);

        $profile = current_tenant_profile();
        if (! $profile) {
            return response()->json(['error' => 'Tenant user profile not found.'], 403);
        }

        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $this->accounting->isConnected()) {
            return response()->json([
                'error' => 'QuickBooks integration is disabled or not connected. Enable it under Integrations → QuickBooks.',
            ], 422);
        }

        $profileId = (int) $profile->getKey();
        $billingType = (int) $validated['billing_type'];

        try {
            PullServiceItemsFromQuickBooks::dispatch($profileId, $billingType);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Could not queue the import job. Check that the central jobs table exists and queue workers are running.',
            ], 500);
        }

        return response()->json([
            'message' => 'QuickBooks service import queued. Service items may take a few minutes to appear.',
        ]);
    }

    public function importChartOfAccounts(Request $request): JsonResponse
    {
        return $this->dispatchImportJob($request, PullChartOfAccountsFromQuickBooks::class, 'QuickBooks chart of accounts sync queued.');
    }

    public function importStatus(Request $request): JsonResponse
    {
        if (! $request->expectsJson()) {
            abort(405, 'This endpoint must be accessed via AJAX or JSON.');
        }

        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        $meta = $integration?->metadata ?? [];

        return response()->json([
            'sync_status' => $integration?->sync_status?->value ?? IntegrationSyncStatus::Pending->value,
            'sync_error_message' => $integration?->sync_error_message,
            'sync_operation' => $meta['sync_operation'] ?? null,
        ]);
    }

    public function clearImportStatus(Request $request): JsonResponse
    {
        if (! $request->expectsJson()) {
            abort(405, 'This endpoint must be accessed via AJAX or JSON.');
        }

        QuickBooksImportStatus::clearActiveImport();

        return response()->json([
            'message' => 'Import status cleared.',
            'sync_status' => IntegrationSyncStatus::Success->value,
            'sync_error_message' => null,
            'sync_operation' => null,
        ]);
    }

    public function importVendors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'existing_mode' => ['nullable', 'string', 'in:update,skip'],
        ]);

        $updateExisting = ($validated['existing_mode'] ?? 'update') !== 'skip';

        return $this->dispatchImportJob(
            $request,
            PullVendorsFromQuickBooks::class,
            'QuickBooks vendor import queued.',
            [$updateExisting],
        );
    }

    public function importBills(Request $request): JsonResponse
    {
        $dates = QuickBooksImportDateRange::validate($request->all());

        return $this->dispatchImportJob(
            $request,
            PullBillsFromQuickBooks::class,
            'QuickBooks bill import queued.',
            [$dates['txn_date_from'], $dates['txn_date_to']],
        );
    }

    public function importBillPayments(Request $request): JsonResponse
    {
        $dates = QuickBooksImportDateRange::validate($request->all());

        return $this->dispatchImportJob(
            $request,
            PullBillPaymentsFromQuickBooks::class,
            'QuickBooks bill payment import queued.',
            [$dates['txn_date_from'], $dates['txn_date_to']],
        );
    }

    /**
     * @param  class-string  $jobClass
     * @param  list<mixed>  $jobArgs
     */
    private function dispatchImportJob(Request $request, string $jobClass, string $successMessage, array $jobArgs = []): JsonResponse
    {
        if (! $request->expectsJson()) {
            abort(405, 'This endpoint must be accessed via AJAX or JSON.');
        }

        $profile = current_tenant_profile();
        if (! $profile) {
            return response()->json(['error' => 'Tenant user profile not found.'], 403);
        }

        $integration = Integration::query()
            ->where('integration_type', IntegrationType::QuickBooks)
            ->first();

        if (! $this->accounting->isConnected()) {
            return response()->json([
                'error' => 'QuickBooks integration is disabled or not connected. Enable it under Integrations → QuickBooks.',
            ], 422);
        }

        $profileId = (int) $profile->getKey();

        Log::info('QuickBooks import: dispatch requested', [
            'job_class' => $jobClass,
            'profile_id' => $profileId,
            'tenant_id' => tenancy()->initialized ? tenancy()->tenant?->getTenantKey() : null,
            'tenancy_initialized' => tenancy()->initialized,
            'queue_connection' => config('queue.default'),
        ]);

        $metadata = $integration->metadata ?? [];
        $metadata['sync_operation'] = $this->syncOperationForJob($jobClass);

        $integration->update([
            'sync_status' => IntegrationSyncStatus::Pending,
            'sync_error_message' => null,
            'metadata' => $metadata,
        ]);

        try {
            $jobClass::dispatch($profileId, ...$jobArgs);
        } catch (\Throwable $e) {
            Log::error('QuickBooks import: dispatch failed', [
                'job_class' => $jobClass,
                'profile_id' => $profileId,
                'tenant_id' => tenancy()->initialized ? tenancy()->tenant?->getTenantKey() : null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Could not queue the import job. Check that the central jobs table exists and queue workers are running.',
            ], 500);
        }

        Log::info('QuickBooks import: job queued', [
            'job_class' => $jobClass,
            'profile_id' => $profileId,
            'tenant_id' => tenancy()->initialized ? tenancy()->tenant?->getTenantKey() : null,
        ]);

        $integration->refresh();

        return response()->json([
            'message' => $successMessage,
            'sync_status' => $integration->sync_status?->value ?? IntegrationSyncStatus::Pending->value,
            'sync_operation' => $metadata['sync_operation'],
        ]);
    }

    private function syncOperationForJob(string $jobClass): string
    {
        return match ($jobClass) {
            PullContactsFromQuickBooks::class => 'customers',
            PullServiceItemsFromQuickBooks::class => 'services',
            PullVendorsFromQuickBooks::class => 'vendors',
            PullChartOfAccountsFromQuickBooks::class => 'chart_of_accounts',
            PullBillsFromQuickBooks::class => 'bills',
            PullBillPaymentsFromQuickBooks::class => 'bill_payments',
            default => 'import',
        };
    }
}
