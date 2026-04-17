<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Payment\Models\PaymentConfiguration;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\Payments\StripeConnectWebhookHandler;
use App\Services\Payments\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    public function connect(StripeService $stripeService)
    {
        $settings = AccountSettings::getCurrent();
        $config = PaymentConfiguration::forStripe($settings);

        if (! $config->stripe_account_id) {
            $account = $stripeService->createConnectedAccount();
            $config->update([
                'stripe_account_id' => $account->id,
                'meta' => array_merge($config->meta ?? [], [
                    'connect_created_at' => now()->toIso8601String(),
                ]),
            ]);
            $stripeService->applyAccountPayloadToConfiguration($config->fresh(), $account->toArray());
        } else {
            $stripeService->ensureRequestedCapabilities($config->stripe_account_id);
        }

        $url = $stripeService->createOnboardingLink($config->stripe_account_id);

        return redirect()->away($url);
    }

    public function return(Request $request, StripeService $stripeService)
    {
        $config = PaymentConfiguration::forStripe(AccountSettings::getCurrent());
        $stripeService->syncAccount($config);

        return redirect()
            ->route('account.payments')
            ->with('success', 'Stripe account updated.');
    }

    /**
     * Stripe calls this when the onboarding link expires or the user navigates away.
     * Issue a fresh AccountLink so they can continue (same pattern as {@see connect()}).
     */
    public function refresh(StripeService $stripeService)
    {
        $config = PaymentConfiguration::forStripe(AccountSettings::getCurrent());

        if (! $config->stripe_account_id) {
            return redirect()
                ->route('account.payments')
                ->with('error', 'No Stripe account found. Connect from Account → Payments first.');
        }

        $stripeService->ensureRequestedCapabilities($config->stripe_account_id);
        $url = $stripeService->createOnboardingLink($config->stripe_account_id);

        return redirect()->away($url);
    }

    /**
     * Tenant-hosted webhook (optional). Prefer the central {@see \App\Http\Controllers\StripeConnectWebhookController}
     * with URL {@code POST /stripe/connect-webhook} on the app domain so one Stripe endpoint serves all tenants.
     */
    public function webhook(Request $request, StripeService $stripeService, StripeConnectWebhookHandler $handler): JsonResponse
    {
        try {
            $payload = StripeConnectWebhookHandler::decodePayloadFromRequest($request);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'invalid_signature'], 400);
        }

        try {
            $handler->handle($payload, $stripeService);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook handler failed', [
                'type' => $payload['type'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'handler_error'], 500);
        }

        return response()->json(['status' => 'success']);
    }
}
