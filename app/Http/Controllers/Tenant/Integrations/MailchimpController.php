<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integrations;

use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationType;
use App\Http\Controllers\Controller;
use App\Jobs\PullContactsFromMailchimp;
use App\Jobs\PushContactsToMailchimp;
use App\Services\MailchimpOAuthService;
use App\Services\MailchimpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MailchimpController extends Controller
{
    protected string $route;

    public function __construct(
        protected MailchimpService $mailchimpService,
        protected MailchimpOAuthService $mailchimpOAuthService,
    ) {
        $this->route = Route::currentRouteName() ?? '';
    }

    public function show(Request $request)
    {
        $oauthNotice = null;
        if ($request->boolean('mailchimp_connected')) {
            $oauthNotice = [
                'type' => 'success',
                'message' => 'Mailchimp connected successfully.',
            ];
        } elseif ($request->filled('mailchimp_error')) {
            $oauthNotice = [
                'type' => 'error',
                'message' => match ($request->query('mailchimp_error')) {
                    'token' => 'Mailchimp did not return a token. Confirm MAILCHIMP_REDIRECT_URI (or APP_URL) matches the redirect URL in your Mailchimp OAuth app exactly, including https. Then click Connect again.',
                    'metadata' => 'Connected to Mailchimp but could not read account metadata. Try again or contact support.',
                    default => 'Mailchimp connection failed. Please try again.',
                },
            ];
        }

        $profile = current_tenant_profile();
        $centralUser = auth()->user();

        $integrationMeta = [
            'id' => IntegrationType::MailChimp->value,
            'type' => IntegrationType::MailChimp->slug(),
            'name' => IntegrationType::MailChimp->label(),
            'description' => IntegrationType::MailChimp->description(),
            'icon' => IntegrationType::MailChimp->icon(),
            'category' => IntegrationType::MailChimp->category(),
            'requires_oauth' => IntegrationType::MailChimp->requiresOAuth(),
        ];

        $currentIntegration = Integration::query()
            ->where('integration_type', IntegrationType::MailChimp)
            ->first();

        $breadcrumbs = [
            'current' => $integrationMeta['name'],
            'links' => [
                ['url' => route('dashboard'), 'name' => 'Dashboard'],
                ['url' => route('integrations'), 'name' => 'Integrations'],
            ],
        ];

        return Inertia::render('Tenant/Integrations/Mailchimp', [
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
            'currentRoute' => $this->route,
            'integration' => $integrationMeta,
            'hasMailchimpToken' => (bool) $currentIntegration?->access_token,
            'currentIntegration' => $currentIntegration ? [
                'id' => $currentIntegration->id,
                'active' => (bool) $currentIntegration->active,
                'last_synced_at' => $currentIntegration->last_synced_at?->toIso8601String(),
                'sync_status' => $currentIntegration->sync_status?->value,
            ] : null,
        ]);
    }

    public function connect(Request $request)
    {
        $profile = current_tenant_profile();
        if (! $profile) {
            return redirect()->route('integrations')->withErrors('Could not resolve your user profile for this workspace.');
        }

        $tenant = tenant();
        if (! $tenant) {
            return redirect()->route('integrations')->withErrors('Could not resolve the current workspace.');
        }

        $redirectUri = config('services.mailchimp.redirect_uri');
        if (! $redirectUri || ! filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            Log::error('Mailchimp OAuth: invalid or missing services.mailchimp.redirect_uri');

            return redirect()->route('integrations')->withErrors(
                'Mailchimp redirect URL is not configured. Set MAILCHIMP_REDIRECT_URI to your central callback URL (see config/services.php).'
            );
        }

        $handoffId = (string) Str::uuid();
        $central = (string) config('tenancy.database.central_connection');

        DB::connection($central)->table('mailchimp_oauth_handoffs')->insert([
            'id' => $handoffId,
            'tenant_id' => $tenant->getTenantKey(),
            'tenant_user_profile_id' => $profile->getKey(),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $params = [
            'response_type' => 'code',
            'client_id' => config('services.mailchimp.client_id'),
            'redirect_uri' => $redirectUri,
            'state' => $handoffId,
        ];

        $url = 'https://login.mailchimp.com/oauth2/authorize?'.http_build_query($params);

        return redirect()->away($url);
    }

    protected function refreshAccessToken(Integration $integration): string
    {
        return $this->mailchimpOAuthService->refreshAccessToken($integration);
    }

    protected function getClient(): \MailchimpMarketing\ApiClient
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::MailChimp)
            ->firstOrFail();

        $serverPrefix = $integration->metadata['dc'] ?? null;

        if (! $serverPrefix) {
            throw new \RuntimeException('Mailchimp server prefix (dc) not found in integration metadata');
        }

        $accessToken = $integration->access_token;

        if ($integration->token_expires_at && now()->isAfter($integration->token_expires_at)) {
            Log::info('Mailchimp token expired, attempting refresh');
            $accessToken = $this->refreshAccessToken($integration);
        }

        if (! $accessToken) {
            throw new \RuntimeException('Mailchimp access token not found');
        }

        $client = new \MailchimpMarketing\ApiClient;
        $client->setConfig([
            'accessToken' => $accessToken,
            'server' => $serverPrefix,
        ]);

        return $client;
    }

    public function lists()
    {
        try {
            $client = $this->getClient();
            $response = $client->lists->getAllLists();

            $lists = [];

            if (is_object($response) && isset($response->lists)) {
                $lists = $response->lists;
            } elseif (is_array($response) && isset($response['lists'])) {
                $lists = $response['lists'];
            } elseif (is_object($response)) {
                $responseArray = json_decode(json_encode($response), true);
                $lists = $responseArray['lists'] ?? [];
            }

            return response()->json([
                'lists' => $lists,
            ]);
        } catch (\Throwable $e) {
            $errorMessage = $this->extractMailchimpError($e);
            Log::error('Mailchimp lists error', [
                'message' => $errorMessage,
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $errorMessage], 500);
        }
    }

    public function createList(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'permission_reminder' => 'required|string|max:255',
        ]);

        try {
            $client = $this->getClient();
            $centralUser = auth()->user();

            $account = $request->attributes->get('tenant_account');
            $companyName = $account?->name ?? config('app.name', 'Your business');

            $fromName = $centralUser?->name ?? trim(($centralUser->first_name ?? '').' '.($centralUser->last_name ?? '')) ?: 'Agent';
            $defaultHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $fromEmail = $centralUser && filter_var($centralUser->email, FILTER_VALIDATE_EMAIL)
                ? $centralUser->email
                : 'no-reply@'.$defaultHost;

            $response = $client->lists->createList([
                'name' => $request->name,
                'contact' => [
                    'company' => $companyName,
                    'address1' => '123 Business Address',
                    'city' => 'Your City',
                    'state' => 'CA',
                    'zip' => '90210',
                    'country' => 'US',
                ],
                'permission_reminder' => $request->permission_reminder,
                'campaign_defaults' => [
                    'from_name' => $fromName,
                    'from_email' => $fromEmail,
                    'subject' => $request->subject,
                    'language' => 'EN_US',
                ],
                'email_type_option' => false,
            ]);

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('Mailchimp createList error - Full Exception', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = $this->extractMailchimpError($e);

            return response()->json(['error' => $errorMessage], 500);
        }
    }

    public function segments(string $listId)
    {
        try {
            $client = $this->getClient();

            $savedSegments = $client->lists->listSegments($listId);

            $segments = [];

            if (isset($savedSegments->segments)) {
                foreach ($savedSegments->segments as $seg) {
                    $segments[] = [
                        'id' => $seg->id,
                        'name' => $seg->name,
                        'type' => 'segment',
                    ];
                }
            }

            return response()->json([
                'segments' => $segments,
            ]);
        } catch (\Throwable $e) {
            $errorMessage = $this->extractMailchimpError($e);
            Log::error('Mailchimp segments/tags error', [
                'list_id' => $listId,
                'message' => $errorMessage,
            ]);

            return response()->json(['error' => $errorMessage], 500);
        }
    }

    public function createSegment(Request $request, string $listId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $client = $this->getClient();

            $response = $client->lists->createSegment($listId, [
                'name' => $request->name,
                'static_segment' => [],
            ]);

            return response()->json($response);
        } catch (\Throwable $e) {
            $errorMessage = $this->extractMailchimpError($e);
            Log::error('Mailchimp createSegment error', [
                'list_id' => $listId,
                'message' => $errorMessage,
            ]);

            return response()->json(['error' => $errorMessage], 500);
        }
    }

    public function pullContacts(Request $request)
    {
        if (! $request->ajax() && ! $request->wantsJson()) {
            abort(405, 'This endpoint must be accessed via AJAX or JSON.');
        }

        $request->validate([
            'type' => 'in:contact,lead',
            'type_id' => 'nullable|integer',
            'list' => 'required|string',
            'segment_id' => 'nullable|string',
        ]);

        $profile = current_tenant_profile();
        if (! $profile) {
            return response()->json(['error' => 'Tenant user profile not found.'], 403);
        }

        PullContactsFromMailchimp::dispatch(
            (int) $profile->getKey(),
            $request->input('type', 'contact'),
            $request->input('type_id'),
            $request->input('list'),
            $request->input('segment_id')
        );

        return response()->json([
            'message' => 'Mailchimp contacts import queued. Records may take a few minutes to populate.',
        ]);
    }

    public function pushContacts(Request $request, string $listId)
    {
        $request->validate([
            'type' => 'required|in:contact,lead',
            'apply_scope' => 'required|in:all,selected,filtered',
            'selected_ids' => 'required_if:apply_scope,selected|array',
            'selected_ids.*' => 'integer',
            'filters.statuses' => 'array',
            'filters.sources' => 'array',
            'filters.priorities' => 'array',
            'filters.types' => 'array',
        ]);

        $profile = current_tenant_profile();
        if (! $profile) {
            return response()->json(['error' => 'Tenant user profile not found.'], 403);
        }

        PushContactsToMailchimp::dispatch(
            $listId,
            $request->input('type'),
            (int) $profile->getKey(),
            $request->input('apply_scope', 'all'),
            $request->input('selected_ids', []),
            $request->input('filters', []),
            null
        );

        return response()->json([
            'message' => 'Contacts batch push to Mailchimp queued successfully.',
        ]);
    }

    public function pushToSegment(Request $request, string $listId, string $segmentId)
    {
        $request->validate([
            'type' => 'required|in:contact,lead',
            'apply_scope' => 'required|in:all,selected,filtered',
            'selected_ids' => 'required_if:apply_scope,selected|array',
            'selected_ids.*' => 'integer',
            'filters.statuses' => 'array',
            'filters.sources' => 'array',
            'filters.priorities' => 'array',
            'filters.types' => 'array',
        ]);

        $profile = current_tenant_profile();
        if (! $profile) {
            return response()->json(['error' => 'Tenant user profile not found.'], 403);
        }

        PushContactsToMailchimp::dispatch(
            $listId,
            $request->input('type'),
            (int) $profile->getKey(),
            $request->input('apply_scope', 'all'),
            $request->input('selected_ids', []),
            $request->input('filters', []),
            $segmentId
        );

        return response()->json([
            'message' => 'Contacts batch push to Mailchimp segment queued successfully.',
        ]);
    }

    public function destroy(Request $request)
    {
        $integration = Integration::query()
            ->where('integration_type', IntegrationType::MailChimp)
            ->first();

        if (! $integration) {
            return redirect()->route('mailchimp')->withErrors('No Mailchimp integration found.');
        }

        $integration->delete();

        return redirect()->route('integrations')->with('success', 'Mailchimp integration has been removed.');
    }

    protected function extractMailchimpError(\Throwable $e): string
    {
        if ($e instanceof \GuzzleHttp\Exception\ClientException
            || $e instanceof \GuzzleHttp\Exception\ServerException) {
            try {
                $response = $e->getResponse();
                $body = json_decode($response->getBody()->getContents(), true);

                if (isset($body['title']) && isset($body['detail'])) {
                    return $body['title'].': '.$body['detail'];
                }

                if (isset($body['title'])) {
                    return $body['title'];
                }

                if (isset($body['detail'])) {
                    return $body['detail'];
                }

                return 'Mailchimp API Error ('.$response->getStatusCode().'): '.$response->getReasonPhrase();
            } catch (\Throwable) {
                // continue
            }
        }

        $message = $e->getMessage();

        if (preg_match('/\{[^{}]*(?:"title"|"detail")[^{}]*\}/s', $message, $matches)) {
            try {
                $jsonError = json_decode($matches[0], true);

                if (isset($jsonError['title']) && isset($jsonError['detail'])) {
                    return $jsonError['title'].': '.$jsonError['detail'];
                }

                if (isset($jsonError['title'])) {
                    return $jsonError['title'];
                }

                if (isset($jsonError['detail'])) {
                    return $jsonError['detail'];
                }
            } catch (\Throwable) {
                // continue
            }
        }

        if (preg_match('/`(\d{3})\s+([^`]+)`/', $message, $matches)) {
            $statusCode = $matches[1];
            $statusText = $matches[2];

            $context = match ($statusCode) {
                '401' => 'Check your API credentials',
                '403' => 'You don\'t have permission for this action. Check your Mailchimp account permissions',
                '404' => 'Resource not found',
                '422' => 'Invalid data provided',
                '429' => 'Rate limit exceeded. Please try again later',
                '500', '502', '503' => 'Mailchimp server error. Please try again later',
                default => 'Please check your request and try again',
            };

            return "Mailchimp Error ({$statusCode}): {$statusText}. {$context}";
        }

        $firstLine = explode("\n", $message)[0];

        if (str_contains($firstLine, 'Client error:') && strlen($firstLine) > 200) {
            return 'Mailchimp API Error: Unable to complete request. Please check your permissions and try again.';
        }

        return $firstLine;
    }
}
