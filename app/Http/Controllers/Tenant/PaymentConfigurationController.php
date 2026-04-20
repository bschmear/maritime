<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Payment\Models\PaymentConfiguration;
use App\Domain\Payment\Models\ProcessorPaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\Payments\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentConfigurationController extends Controller
{
    public function stripeInformation(): Response
    {
        return Inertia::render('Tenant/Account/StripeInformation');
    }

    /**
     * Payments hub: current processor, links to Stripe / QuickBooks detail pages (no external API sync).
     */
    public function index(): Response
    {
        $settings = AccountSettings::getCurrent();
        $stripeConfig = PaymentConfiguration::forStripe($settings);
        $qbConfig = PaymentConfiguration::forQuickbooks($settings);
        $qbConnected = $qbConfig->quickbooksConnected();
        $stripeClaimed = PaymentConfiguration::stripeConnectClaimed($settings);
        $qbMeta = $qbConfig->meta ?? [];

        $currentProcessor = $qbConnected
            ? 'quickbooks'
            : ($stripeClaimed ? 'stripe' : null);

        return Inertia::render('Tenant/Account/Payments', [
            'current_processor' => $currentProcessor,
            'stripe' => [
                'account_id' => $stripeConfig->stripe_account_id,
                'ready' => $stripeConfig->stripeReadyForCharges(),
                'status_label' => $this->stripeStatusLabel($stripeConfig),
            ],
            'quickbooks' => [
                'connected' => $qbConnected,
                'company_name' => $qbMeta['qbo_company_name'] ?? null,
                'environment' => $qbMeta['qbo_environment'] ?? config('services.quickbooks.environment', 'sandbox'),
                'status_label' => $qbConnected ? 'Connected' : 'Not connected',
            ],
        ]);
    }

    /**
     * Stripe Connect + checkout method toggles (detail page).
     */
    public function stripePage(StripeService $stripeService): Response
    {
        $settings = AccountSettings::getCurrent();
        $stripeConfig = PaymentConfiguration::forStripe($settings);
        $qbConfig = PaymentConfiguration::forQuickbooks($settings);
        $qbConnected = $qbConfig->quickbooksConnected();

        if ($stripeConfig->stripe_account_id && ! $qbConnected) {
            try {
                $stripeService->syncAccount($stripeConfig);
                $stripeConfig->refresh();
            } catch (\Throwable $e) {
                Log::warning('Stripe account sync failed on Stripe payment page', [
                    'configuration_id' => $stripeConfig->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $stripeConfig->load([
            'processorPaymentMethods' => fn ($q) => $q->orderBy('payment_method_code'),
            'processorPaymentMethods.methodConfig',
        ]);

        $methods = $stripeConfig->processorPaymentMethods->map(function (ProcessorPaymentMethod $pivot) {
            return [
                'code' => $pivot->payment_method_code,
                'label' => $pivot->methodConfig?->label ?? $pivot->payment_method_code,
                'is_enabled' => $pivot->is_enabled,
            ];
        })->values()->all();

        $stripeClaimed = PaymentConfiguration::stripeConnectClaimed($settings);
        $meta = $stripeConfig->meta ?? [];

        return Inertia::render('Tenant/Account/Payments/Stripe', [
            'stripe' => [
                'account_id' => $stripeConfig->stripe_account_id,
                'charges_enabled' => $stripeConfig->stripe_charges_enabled,
                'payouts_enabled' => $stripeConfig->stripe_payouts_enabled,
                'details_submitted' => (bool) ($meta['details_submitted'] ?? false),
                'email' => $meta['email'] ?? null,
                'ready' => $stripeConfig->stripeReadyForCharges(),
                'card_payments_capability' => $stripeConfig->stripeCardPaymentsCapability(),
                'transfers_capability' => $stripeConfig->stripeTransfersCapability(),
                'setup_hint' => $stripeConfig->stripeSetupHint(),
                'status_label' => $this->stripeStatusLabel($stripeConfig),
                'connected_at' => $meta['connect_created_at'] ?? $meta['connected_at'] ?? null,
                /** Stripe Connect does not store OAuth expiry in Maritime; UI explains. */
                'access_token_expires_at' => null,
                'refresh_token_expires_at' => null,
            ],
            'paymentMethods' => $methods,
            'can_connect_stripe' => ! $qbConnected,
            'can_use_stripe_payment_methods' => ! $qbConnected,
        ]);
    }

    /**
     * QuickBooks Online OAuth + company details (detail page).
     */
    public function quickbooksPage(Request $request): Response
    {
        $settings = AccountSettings::getCurrent();
        $qbConfig = PaymentConfiguration::forQuickbooks($settings);
        $qbMeta = $qbConfig->meta ?? [];
        $qbConnected = $qbConfig->quickbooksConnected();
        $stripeClaimed = PaymentConfiguration::stripeConnectClaimed($settings);

        $oauthNotice = $this->quickbooksOAuthNotice($request);

        return Inertia::render('Tenant/Account/Payments/Quickbooks', [
            'quickbooks' => [
                'connected' => $qbConnected,
                'realm_id' => $qbConfig->qbo_realm_id,
                'environment' => $qbMeta['qbo_environment'] ?? config('services.quickbooks.environment', 'sandbox'),
                'company_name' => $qbMeta['qbo_company_name'] ?? null,
                'legal_name' => $qbMeta['qbo_legal_name'] ?? null,
                'country' => $qbMeta['qbo_country'] ?? null,
                'email' => $qbMeta['qbo_email'] ?? null,
                'connected_at' => $qbMeta['qbo_connected_at'] ?? null,
                'token_expires_at' => optional($qbConfig->qbo_token_expires_at)->toIso8601String(),
                'refresh_token_expires_at' => $qbMeta['qbo_refresh_token_expires_at'] ?? null,
            ],
            'can_connect_quickbooks' => ! $stripeClaimed,
            'oauthNotice' => $oauthNotice,
        ]);
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function quickbooksOAuthNotice(Request $request): ?array
    {
        if ($request->boolean('qbo_connected')) {
            return ['type' => 'success', 'message' => 'QuickBooks Online connected successfully.'];
        }
        if ($request->filled('qbo_error')) {
            return [
                'type' => 'error',
                'message' => match ($request->query('qbo_error')) {
                    'token' => 'QuickBooks did not return a token. Confirm QUICKBOOKS_REDIRECT_URI matches the redirect URL registered in your Intuit app exactly, then try again.',
                    'stripe_active' => 'QuickBooks was not connected because this workspace already has Stripe. Disconnect Stripe on the Stripe page first, then connect QuickBooks Online.',
                    default => 'QuickBooks connection failed. Please try again.',
                },
            ];
        }

        return null;
    }

    private function stripeStatusLabel(PaymentConfiguration $stripeConfig): string
    {
        if ($stripeConfig->stripeReadyForCharges()) {
            return 'Ready';
        }
        if ($stripeConfig->stripe_account_id && $stripeConfig->stripeCardPaymentsCapability() === 'pending') {
            return 'Verifying';
        }
        if ($stripeConfig->stripe_account_id && ($stripeConfig->meta['details_submitted'] ?? false)) {
            return 'Finishing setup';
        }
        if ($stripeConfig->stripe_account_id) {
            return 'Incomplete';
        }

        return 'Not connected';
    }

    /**
     * Pull the latest Connect account + capability state from Stripe and persist it locally.
     */
    public function syncFromStripe(StripeService $stripeService): RedirectResponse
    {
        $settings = AccountSettings::getCurrent();

        if (PaymentConfiguration::forQuickbooks($settings)->quickbooksConnected()) {
            return back()->with(
                'error',
                'QuickBooks Online is the active payment connection. Disconnect QuickBooks before using Stripe on this page.'
            );
        }

        $stripeConfig = PaymentConfiguration::forStripe($settings);

        if (! $stripeConfig->stripe_account_id) {
            return back()->with('error', 'Connect a Stripe account first.');
        }

        try {
            $stripeService->syncAccount($stripeConfig);
            $stripeConfig->refresh();
        } catch (\Throwable $e) {
            Log::warning('Manual Stripe sync failed', [
                'configuration_id' => $stripeConfig->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not reach Stripe to verify status. Check your network and try again, or wait a minute if Stripe is busy.');
        }

        if ($stripeConfig->stripeReadyForCharges()) {
            return back()->with('success', 'Stripe status updated — card payments are active and you can accept online invoice payments.');
        }

        $card = $stripeConfig->stripeCardPaymentsCapability();

        $message = match ($card) {
            'pending' => 'Stripe status updated. Card payments are still pending activation (Stripe often finishes this within a few minutes after you complete onboarding).',
            'inactive' => 'Stripe status updated. Card payments still need attention — use Continue setup to finish any requirements Stripe shows.',
            default => 'Stripe status updated. Card payments are not active yet — open Continue setup if Stripe still needs business or bank details.',
        };

        return back()->with('success', $message);
    }

    public function updateMethod(Request $request)
    {
        $settings = AccountSettings::getCurrent();

        if (PaymentConfiguration::forQuickbooks($settings)->quickbooksConnected()) {
            return back()->with(
                'error',
                'Payment method toggles apply to Stripe only. Disconnect QuickBooks Online to manage Stripe payment methods.'
            );
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'is_enabled' => 'required|boolean',
        ]);

        $stripeConfig = PaymentConfiguration::forStripe($settings);

        ProcessorPaymentMethod::query()
            ->where('configuration_id', $stripeConfig->id)
            ->where('payment_method_code', $validated['code'])
            ->update(['is_enabled' => $validated['is_enabled']]);

        return back()->with('success', 'Payment method updated.');
    }
}
