<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Database\Models\Domain;
use Exception;
use App\Domain\TenantUser\Models\TenantUser;
use App\Domain\Role\Models\Role;

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

        if ($user->subscribed()) {
            return redirect()->route('dashboard')
                ->with('error', 'You already have an active subscription.');
        }

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
    $tenant = null;

    try {
        // Add payment method to user
        $user->updateDefaultPaymentMethod($request->payment_method);

        // Get Stripe price ID
        $stripePriceId = $plan->getStripePriceId($request->billing_cycle);
        if (!$stripePriceId) {
            throw new Exception('Stripe price ID not configured for this plan.');
        }

        // Check for existing account first
        $account = $user->ownedAccounts()->first();

        if (!$account) {
            // Generate unique 6-digit subdomain
            do {
                $subdomain = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                $domainName = $subdomain . '.' . config('app.domain', 'localhost');
            } while (Domain::where('domain', $domainName)->exists());

            $tenantId = \Illuminate\Support\Str::uuid()->toString();
            $schemaName = 'tenant' . $tenantId;

            // Create schema directly
            Log::info('Creating schema directly in controller', ['schema' => $schemaName]);
            $centralConnection = DB::connection('pgsql');
            $centralConnection->statement("CREATE SCHEMA IF NOT EXISTS \"{$schemaName}\"");

            $schemaExists = $centralConnection->select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                [$schemaName]
            );

            Log::info('Schema created', [
                'schema' => $schemaName,
                'exists' => !empty($schemaExists),
            ]);

            // Create tenant record
            $tenant = Tenant::create(['id' => $tenantId]);

            $tenant->domains()->create([
                'domain' => $domainName,
            ]);

            // Tenant setup
            try {
                sleep(1); // optional delay for jobs
                $account = Account::create([
                    'name' => $request->account_name,
                    'owner_id' => $user->id,
                    'tenant_id' => $tenant->id,
                ]);

                $account->users()->attach($user->id, ['role' => 'owner']);

                // Copy authenticated user to tenant users table
                $this->copyUserToTenant($user, $tenant);

            } catch (Exception $e) {
                Log::error('Tenant setup failed', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                // do not rollback tenant or schema
            }
        }

        // Create subscription with base plan
        $subscriptionBuilder = $user->newSubscription('default', $stripePriceId)
            ->trialDays(14)
            ->quantity(1); // Base quantity (included seats covered by base plan)

        // Note: Extra seats will be added as separate line items when users are invited
        // This allows for proper billing separation between included and extra seats

        $subscription = $subscriptionBuilder->create($request->payment_method);

        // Link subscription to account and plan
        if ($account) {
            $subscription->update([
                'account_id' => $account->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $request->billing_cycle,
            ]);

            Log::info('Subscription linked to account', [
                'subscription_id' => $subscription->id,
                'account_id' => $account->id,
                'plan_id' => $plan->id,
                'initial_quantity' => 1,
            ]);
        }

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

        $existingAccount = $user->ownedAccounts()->first();
        $defaultAccountName = $existingAccount ? $existingAccount->name : ($user->name . "'s Account");
        $addOns = $plan->items()->where('active', true)->get();

        return Inertia::render('Checkout/Cart', [
            'plan' => $plan,
            'billingCycle' => $billingCycle,
            'addOns' => $addOns,
            'defaultAccountName' => $defaultAccountName,
            'hasExistingAccount' => (bool) $existingAccount,
        ]);
    }

    /**
     * Copy the authenticated user to the tenant's users table.
     */
    private function copyUserToTenant(\App\Models\User $user, \App\Models\Tenant $tenant): void
    {
        try {
            // Switch to tenant context
            tenancy()->initialize($tenant);

            // Parse name into first and last name
            $nameParts = explode(' ', $user->name, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            // Create tenant user
            TenantUser::create([
                'display_name' => $user->name,
                'first_name' => $firstName,
                'last_name' => $lastName,
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
