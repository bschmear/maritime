<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Payment\Models\PaymentConfiguration;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\Payments\StripeConnectWebhookHandler;
use App\Services\Payments\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    public function connect(StripeService $stripeService, Request $request): RedirectResponse
    {
        if ($request->query('from') === 'onboarding') {
            session(['stripe_connect_from_onboarding' => true]);
        } else {
            session()->forget('stripe_connect_from_onboarding');
        }

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

        $fromOnboarding = (bool) session('stripe_connect_from_onboarding');
        $returnUrl = $fromOnboarding
            ? route('stripe.return', ['from' => 'onboarding'])
            : route('stripe.return');
        $refreshUrl = $fromOnboarding
            ? route('stripe.refresh', ['from' => 'onboarding'])
            : route('stripe.refresh');

        $url = $stripeService->createOnboardingLink($config->stripe_account_id, $returnUrl, $refreshUrl);

        return redirect()->away($url);
    }

    /**
     * Clear Stripe Connect state so the tenant can choose QuickBooks (or reconnect Stripe) instead.
     */
    public function disconnect(): RedirectResponse
    {
        $config = PaymentConfiguration::forStripe(AccountSettings::getCurrent());

        if (! $config->stripe_account_id) {
            return redirect()
                ->route('account.payments')
                ->with('error', 'Stripe is not connected.');
        }

        $config->update([
            'stripe_account_id' => null,
            'stripe_charges_enabled' => false,
            'stripe_payouts_enabled' => false,
            'stripe_publishable_key' => null,
            'stripe_secret_key_enc' => null,
            'meta' => array_merge($config->meta ?? [], [
                'stripe_disconnected_at' => now()->toIso8601String(),
            ]),
        ]);

        return redirect()
            ->route('account.payments')
            ->with('success', 'Stripe has been disconnected from this workspace.');
    }

    public function return(Request $request, StripeService $stripeService): RedirectResponse
    {
        $settings = AccountSettings::getCurrent();

        $config = PaymentConfiguration::forStripe($settings);
        $stripeService->syncAccount($config);

        $fromOnboarding = $request->query('from') === 'onboarding';
        if ($fromOnboarding) {
            session()->forget('stripe_connect_from_onboarding');
        } else {
            $fromOnboarding = (bool) session()->pull('stripe_connect_from_onboarding', false);
        }

        if ($fromOnboarding) {
            return redirect()
                ->route('dashboard', ['onboarding' => 'stripe-return'])
                ->with('success', 'Stripe account updated.');
        }

        return redirect()
            ->route('account.payments')
            ->with('success', 'Stripe account updated.');
    }

    /**
     * Stripe calls this when the onboarding link expires or the user navigates away.
     * Issue a fresh AccountLink so they can continue (same pattern as {@see connect()}).
     */
    public function refresh(Request $request, StripeService $stripeService): RedirectResponse
    {
        $settings = AccountSettings::getCurrent();

        $config = PaymentConfiguration::forStripe($settings);

        if (! $config->stripe_account_id) {
            return redirect()
                ->route('account.payments')
                ->with('error', 'No Stripe account found. Open Payments → Stripe and connect first.');
        }

        $stripeService->ensureRequestedCapabilities($config->stripe_account_id);

        $fromOnboarding = $request->query('from') === 'onboarding' || (bool) session('stripe_connect_from_onboarding');
        $returnUrl = $fromOnboarding
            ? route('stripe.return', ['from' => 'onboarding'])
            : route('stripe.return');
        $refreshUrl = $fromOnboarding
            ? route('stripe.refresh', ['from' => 'onboarding'])
            : route('stripe.refresh');

        $url = $stripeService->createOnboardingLink($config->stripe_account_id, $returnUrl, $refreshUrl);

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
