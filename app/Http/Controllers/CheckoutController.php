<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    /**
     * Show the plan selection page
     */
    public function plans(Request $request)
    {
        $plans = Plan::active()->orderBy('monthly_price')->get();
        
        $selectedPlanId = $request->query('plan');
        $billingCycle = $request->query('billing', 'monthly');

        return Inertia::render('Checkout/Plans', [
            'plans' => $plans,
            'selectedPlanId' => $selectedPlanId ? (int) $selectedPlanId : null,
            'billingCycle' => $billingCycle,
        ]);
    }

    /**
     * Show the checkout page
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'account_name' => 'required|string|max:255',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $billingCycle = $request->billing_cycle;
        $accountName = $request->account_name;
        $user = Auth::user();

        // Check if user already has a subscription
        if ($user->subscribed()) {
            return redirect()->route('dashboard')
                ->with('error', 'You already have an active subscription.');
        }

        // Create or get Stripe customer and setup intent
        $user->createOrGetStripeCustomer();
        $intent = $user->createSetupIntent();

        return Inertia::render('Checkout/Checkout', [
            'plan' => $plan,
            'billingCycle' => $billingCycle,
            'stripeKey' => config('cashier.key'),
            'intent' => $intent,
            'accountName' => $accountName,
        ]);
    }

    /**
     * Process the checkout and create subscription
     */
    public function process(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'payment_method' => 'required|string',
            'account_name' => 'required|string|max:255',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = Auth::user();

        try {
            DB::beginTransaction();

            // Add payment method to user
            $user->updateDefaultPaymentMethod($request->payment_method);

            // Get the Stripe price ID based on billing cycle
            $stripePriceId = $plan->getStripePriceId($request->billing_cycle);

            if (!$stripePriceId) {
                throw new \Exception('Stripe price ID not configured for this plan.');
            }

            // Create subscription with 14-day trial
            $subscription = $user->newSubscription('default', $stripePriceId)
                ->trialDays(14)
                ->create($request->payment_method);

            // Only create account AFTER successful payment
            $account = $user->ownedAccounts()->first();
            
            if (!$account) {
                $account = Account::create([
                    'name' => $request->account_name,
                    'owner_id' => $user->id,
                ]);

                // Attach user to account as owner
                $account->users()->attach($user->id, ['role' => 'owner']);
            }

            DB::commit();

            return redirect()->route('dashboard')
                ->with('success', 'Subscription created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors([
                'error' => 'Payment failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Show the cart/review page before checkout
     */
    public function cart(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $billingCycle = $request->billing_cycle;
        $user = Auth::user();

        // Check if user already has an account
        $existingAccount = $user->ownedAccounts()->first();
        
        // Prepare default account name (don't create yet)
        $defaultAccountName = $existingAccount ? $existingAccount->name : ($user->name . "'s Account");

        // Get available add-ons for this plan
        $addOns = $plan->items()->where('active', true)->get();

        return Inertia::render('Checkout/Cart', [
            'plan' => $plan,
            'billingCycle' => $billingCycle,
            'addOns' => $addOns,
            'defaultAccountName' => $defaultAccountName,
            'hasExistingAccount' => (bool) $existingAccount,
        ]);
    }
}

