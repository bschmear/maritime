<?php

namespace App\Http\Controllers;

use App\Domain\Role\Models\Role;
use App\Domain\User\Models\User as TenantUserModel;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Tenant;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Stancl\Tenancy\Database\Models\Domain;

class CheckoutController extends Controller
{
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
            $user->updateDefaultPaymentMethod($request->payment_method);

            $stripePriceId = $plan->getStripePriceId($request->billing_cycle);
            if (! $stripePriceId) {
                throw new Exception('Stripe price ID not configured for this plan.');
            }

            $account = $this->provisionNewAccount($user, $request->account_name);

            $subscriptionType = 'account_'.$account->id;

            $subscription = $user->newSubscription($subscriptionType, $stripePriceId)
                ->trialDays(14)
                ->quantity(1)
                ->create($request->payment_method);

            $subscription->update([
                'account_id' => $account->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $request->billing_cycle,
            ]);

            Log::info('Subscription linked to account', [
                'subscription_id' => $subscription->id,
                'account_id' => $account->id,
                'plan_id' => $plan->id,
                'cashier_type' => $subscriptionType,
                'initial_quantity' => 1,
            ]);

            return redirect()->route('dashboard')
                ->with('success', 'Subscription created successfully!');
        } catch (Exception $e) {
            Log::error('Checkout process failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Payment or tenant setup failed. Please contact support.',
            ]);
        }
    }

    public function cart(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $billingCycle = $request->billing_cycle;
        $user = Auth::user();

        $ownedCount = $user->ownedAccounts()->count();
        $baseLabel = $user->full_name ?: $user->name ?: $user->email;
        $defaultAccountName = $ownedCount > 0
            ? trim($baseLabel.' Workspace '.($ownedCount + 1))
            : ($baseLabel."'s Account");
        $addOns = $plan->items()->where('active', true)->get();

        return Inertia::render('Checkout/Cart', [
            'plan' => $plan,
            'billingCycle' => $billingCycle,
            'addOns' => $addOns,
            'defaultAccountName' => $defaultAccountName,
            'hasExistingAccount' => $ownedCount > 0,
        ]);
    }

    /**
     * Provision a new tenant schema, tenant record, domain, and central account for checkout.
     */
    private function provisionNewAccount(\App\Models\User $user, string $accountName): Account
    {
        do {
            $subdomain = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $domainName = $subdomain.'.'.config('app.domain', 'localhost');
        } while (Domain::where('domain', $domainName)->exists());

        $tenantId = \Illuminate\Support\Str::uuid()->toString();
        $schemaName = 'tenant'.$tenantId;

        Log::info('Creating schema for new account checkout', ['schema' => $schemaName]);

        $centralConnection = DB::connection('pgsql');
        $centralConnection->statement("CREATE SCHEMA IF NOT EXISTS \"{$schemaName}\"");

        $tenant = Tenant::create(['id' => $tenantId]);

        $tenant->domains()->create([
            'domain' => $domainName,
        ]);

        try {
            sleep(1);

            $account = Account::create([
                'name' => $accountName,
                'owner_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);

            $account->users()->attach($user->id, ['role' => 'owner']);

            $this->copyUserToTenant($user, $tenant);

            return $account;
        } catch (Exception $e) {
            Log::error('Tenant setup failed during checkout', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Copy the authenticated user to the tenant's users table.
     */
    private function copyUserToTenant(\App\Models\User $user, \App\Models\Tenant $tenant): void
    {
        try {
            // Switch to tenant context
            tenancy()->initialize($tenant);

            // Create tenant user with proper first/last name fields
            TenantUserModel::create([
                'display_name' => trim($user->first_name.' '.$user->last_name),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'current_role' => $this->getAdminRoleId(), // Assign admin role
            ]);

            Log::info('User copied to tenant', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'email' => $user->email,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to copy user to tenant', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw exception - tenant creation should continue
        } finally {
            // Reset tenancy context
            tenancy()->end();
        }
    }

    /**
     * Get the admin role ID from the tenant database.
     */
    private function getAdminRoleId(): ?int
    {
        try {
            return Role::where('slug', 'admin')->first()?->id;
        } catch (Exception $e) {
            Log::error('Failed to get admin role ID', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
