<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Payments\StripeConnectWebhookHandler;
use App\Services\Payments\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Platform (central) Stripe Connect webhook — one URL for all tenants.
 * Register in Stripe Dashboard with Connect enabled; signing secret → {@code STRIPE_WEBHOOK_SECRET}.
 */
class StripeConnectWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeConnectWebhookHandler $handler,
        StripeService $stripeService,
    ): JsonResponse {
        try {
            $payload = StripeConnectWebhookHandler::decodePayloadFromRequest($request);
        } catch (\Throwable $e) {
            Log::warning('Stripe Connect webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $connectedAccountId = $payload['account'] ?? null;
        if ($connectedAccountId === null && ($payload['type'] ?? null) === 'account.updated') {
            $obj = $payload['data']['object'] ?? [];
            $connectedAccountId = is_array($obj) ? ($obj['id'] ?? null) : null;
        }

        if (! is_string($connectedAccountId) || $connectedAccountId === '') {
            Log::info('Stripe Connect webhook: missing connected account id', [
                'type' => $payload['type'] ?? null,
            ]);

            return response()->json(['status' => 'ignored'], 200);
        }

        $tenant = $this->findTenantByStripeAccountId($connectedAccountId);
        if ($tenant === null) {
            Log::info('Stripe Connect webhook: no tenant owns this Stripe account', [
                'stripe_account_id' => $connectedAccountId,
                'type' => $payload['type'] ?? null,
            ]);

            return response()->json(['status' => 'no_tenant'], 200);
        }

        try {
            Tenancy::initialize($tenant);
            $handler->handle($payload, $stripeService);
        } catch (\Throwable $e) {
            Log::error('Stripe Connect webhook handler failed', [
                'tenant_id' => $tenant->id,
                'type' => $payload['type'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'handler_error'], 500);
        } finally {
            Tenancy::end();
        }

        return response()->json(['status' => 'success']);
    }

    private function findTenantByStripeAccountId(string $stripeAccountId): ?Tenant
    {
        /** @var Tenant|null $found */
        $found = null;

        foreach (Tenant::query()->cursor() as $tenant) {
            Tenancy::initialize($tenant);

            try {
                $matches = \App\Domain\Payment\Models\PaymentConfiguration::query()
                    ->where('stripe_account_id', $stripeAccountId)
                    ->exists()
                    || \App\Models\PaymentAccount::query()
                        ->where('provider', 'stripe')
                        ->where('external_account_id', $stripeAccountId)
                        ->exists();

                if ($matches) {
                    $found = $tenant;

                    break;
                }
            } finally {
                Tenancy::end();
            }
        }

        return $found;
    }
}
