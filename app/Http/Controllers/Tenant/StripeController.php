<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Services\Payments\StripeService;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    public function connect(StripeService $stripeService)
    {
        $tenant = auth()->user()->tenant;
        $settings = $tenant->accountSettings;

        // Create Stripe account
        $account = $stripeService->createConnectedAccount();

        // Save to DB
        $paymentAccount = PaymentAccount::create([
            'account_settings_id' => $settings->id,
            'provider' => 'stripe',
            'external_account_id' => $account->id,
        ]);

        // Generate onboarding link
        $url = $stripeService->createOnboardingLink($account->id);

        return redirect($url);
    }

    public function return(Request $request, StripeService $stripeService)
    {
        $tenant = auth()->user()->tenant;
        $settings = $tenant->accountSettings;

        $account = PaymentAccount::where('account_settings_id', $settings->id)
            ->where('provider', 'stripe')
            ->firstOrFail();

        $stripeService->syncAccount($account);

        return redirect('/settings/payments')->with('success', 'Stripe connected');
    }

    public function refresh()
    {
        return redirect('/settings/payments')->with('error', 'Stripe onboarding expired. Try again.');
    }
}
