<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Payment\Models\PaymentConfiguration;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\Payments\StripeService;
use Illuminate\Http\Request;

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

    public function refresh()
    {
        return redirect()
            ->route('account.payments')
            ->with('error', 'Stripe onboarding expired. Try again.');
    }
}
