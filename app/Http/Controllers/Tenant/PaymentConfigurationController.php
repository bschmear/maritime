<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Payment\Models\PaymentConfiguration;
use App\Domain\Payment\Models\ProcessorPaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\Payments\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentConfigurationController extends Controller
{
    public function index(StripeService $stripeService): Response
    {
        $settings = AccountSettings::getCurrent();
        $stripeConfig = PaymentConfiguration::forStripe($settings);

        if ($stripeConfig->stripe_account_id) {
            try {
                $stripeService->syncAccount($stripeConfig);
                $stripeConfig->refresh();
            } catch (\Throwable $e) {
                Log::warning('Stripe account sync failed on payments settings', [
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

        return Inertia::render('Tenant/Account/Payments', [
            'stripe' => [
                'account_id' => $stripeConfig->stripe_account_id,
                'charges_enabled' => $stripeConfig->stripe_charges_enabled,
                'payouts_enabled' => $stripeConfig->stripe_payouts_enabled,
                'details_submitted' => (bool) ($stripeConfig->meta['details_submitted'] ?? false),
                'email' => $stripeConfig->meta['email'] ?? null,
                'ready' => $stripeConfig->stripeReadyForCharges(),
                'card_payments_capability' => $stripeConfig->stripeCardPaymentsCapability(),
                'transfers_capability' => $stripeConfig->stripeTransfersCapability(),
                'setup_hint' => $stripeConfig->stripeSetupHint(),
            ],
            'paymentMethods' => $methods,
        ]);
    }

    public function updateMethod(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'is_enabled' => 'required|boolean',
        ]);

        $stripeConfig = PaymentConfiguration::forStripe(AccountSettings::getCurrent());

        ProcessorPaymentMethod::query()
            ->where('configuration_id', $stripeConfig->id)
            ->where('payment_method_code', $validated['code'])
            ->update(['is_enabled' => $validated['is_enabled']]);

        return back()->with('success', 'Payment method updated.');
    }
}
