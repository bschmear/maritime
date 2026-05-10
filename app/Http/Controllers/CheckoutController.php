<?php

namespace App\Http\Controllers;

use App\Domain\Role\Models\Role;
use App\Domain\User\Models\User as TenantUserModel;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\WorkspaceNavCache;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Cashier\Subscription as CashierSubscription;
use Stancl\Tenancy\Database\Models\Domain;

class CheckoutController extends Controller
{
    public function plans(Request $request)
    {
        $plans = Plan::active()->orderBy('monthly_price')->get();

        $selectedPlanId = $request->query('plan');
        $rawBilling = $request->query('billing', 'monthly');
        $billingCycle = in_array($rawBilling, ['yearly', 'annual'], true) ? 'yearly' : 'monthly';

        $prefilledExistingAccountId = null;
        if ($request->filled('existing_account_id')) {
            $candidate = Account::query()
                ->where('id', $request->integer('existing_account_id'))
                ->where('owner_id', Auth::id())
                ->first();
            if ($candidate && ! $candidate->hasActiveSubscription()) {
                $prefilledExistingAccountId = (int) $candidate->id;
            }
        }

        return Inertia::render('Checkout/Plans', [
            'plans' => $plans,
            'selectedPlanId' => $selectedPlanId ? (int) $selectedPlanId : null,
            'billingCycle' => $billingCycle,
            'prefilled_existing_account_id' => $prefilledExistingAccountId,
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'account_name' => 'required_without:existing_account_id|string|max:255',
            'existing_account_id' => 'nullable|integer|exists:accounts,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $billingCycle = $request->billing_cycle;
        $user = Auth::user();

        $existingAccount = $this->resolveOwnedAccountWithoutSubscription($user, $request->integer('existing_account_id') ?: null);

        if ($request->filled('existing_account_id') && ! $existingAccount) {
            return back()->withErrors([
                'existing_account_id' => 'That workspace is not available for checkout, or it already has an active subscription.',
            ]);
        }

        $accountName = $existingAccount
            ? $existingAccount->name
            : (string) $request->input('account_name');

        $user->createOrGetStripeCustomer();
        $intent = $user->createSetupIntent();

        return Inertia::render('Checkout/Checkout', [
            'plan' => $plan,
            'billingCycle' => $billingCycle,
            'stripeKey' => config('cashier.key'),
            'intent' => $intent,
            'accountName' => $accountName,
            'existingAccountId' => $existingAccount?->id,
        ]);
    }

    public function process(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'payment_method' => 'required|string',
            'account_name' => 'required_without:existing_account_id|string|max:255',
            'existing_account_id' => 'nullable|integer|exists:accounts,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = Auth::user();

        try {
            $user->updateDefaultPaymentMethod($request->payment_method);

            $stripePriceId = $plan->getStripePriceId($request->billing_cycle);
            if (! $stripePriceId) {
                throw new Exception('Stripe price ID not configured for this plan.');
            }

            $existingAccount = $this->resolveOwnedAccountWithoutSubscription($user, $request->integer('existing_account_id') ?: null);

            if ($request->filled('existing_account_id')) {
                if (! $existingAccount) {
                    return back()->withErrors([
                        'existing_account_id' => 'That workspace is not available for checkout, or it already has an active subscription.',
                    ]);
                }

                return $this->completeCheckoutForExistingAccount(
                    $user,
                    $existingAccount,
                    $plan,
                    $request->string('billing_cycle')->toString(),
                    $request->string('payment_method')->toString()
                );
            }

            return $this->completeCheckoutForNewAccount(
                $user,
                (string) $request->input('account_name'),
                $plan,
                $request->string('billing_cycle')->toString(),
                $request->string('payment_method')->toString()
            );
        } catch (\Throwable $e) {
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

        $reactivableAccounts = $user->ownedAccounts()
            ->orderBy('name')
            ->get()
            ->filter(fn (Account $a) => ! $a->hasActiveSubscription())
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
            ])
            ->values();

        $prefilledExistingAccountId = null;
        if ($request->filled('existing_account_id')) {
            $candidate = $this->resolveOwnedAccountWithoutSubscription($user, $request->integer('existing_account_id'));
            if ($candidate) {
                $prefilledExistingAccountId = (int) $candidate->id;
            }
        }

        return Inertia::render('Checkout/Cart', [
            'plan' => $plan,
            'billingCycle' => $billingCycle,
            'addOns' => $addOns,
            'defaultAccountName' => $defaultAccountName,
            'hasExistingAccount' => $ownedCount > 0,
            'reactivable_accounts' => $reactivableAccounts,
            'prefilled_existing_account_id' => $prefilledExistingAccountId,
        ]);
    }

    /**
     * An owned account that does not already have an active Cashier subscription (may be reactivated).
     */
    private function resolveOwnedAccountWithoutSubscription(\App\Models\User $user, ?int $accountId): ?Account
    {
        if ($accountId === null || $accountId === 0) {
            return null;
        }

        $account = Account::query()
            ->where('id', $accountId)
            ->where('owner_id', $user->id)
            ->first();

        if (! $account) {
            return null;
        }

        $account->loadMissing('owner');

        if ($account->hasActiveSubscription()) {
            return null;
        }

        return $account;
    }

    /**
     * Checkout for an account that already has a tenant — Stripe only.
     */
    private function completeCheckoutForExistingAccount(
        \App\Models\User $user,
        Account $account,
        Plan $plan,
        string $billingCycle,
        string $paymentMethodId
    ) {
        $subscriptionType = 'account_'.$account->id;
        $seatQuantity = max(1, $account->users()->count());

        $subscription = $user->newSubscription($subscriptionType, $plan->getStripePriceId($billingCycle))
            ->trialDays(14)
            ->quantity($seatQuantity)
            ->create($paymentMethodId);

        $subscription->update([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
        ]);

        Log::info('Subscription linked to existing account', [
            'subscription_id' => $subscription->id,
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'cashier_type' => $subscriptionType,
            'initial_quantity' => $seatQuantity,
        ]);

        WorkspaceNavCache::forgetForAccount($account);

        return redirect()->route('dashboard')
            ->with('success', 'Subscription created successfully!');
    }

    /**
     * New workspace: central account without tenant first, Stripe subscription, then tenant + migrations.
     */
    private function completeCheckoutForNewAccount(
        \App\Models\User $user,
        string $accountName,
        Plan $plan,
        string $billingCycle,
        string $paymentMethodId
    ) {
        $account = $this->createPendingAccountWithoutTenant($user, $accountName);
        $subscriptionType = 'account_'.$account->id;
        $stripePriceId = $plan->getStripePriceId($billingCycle);
        $seatQuantity = max(1, $account->users()->count());

        $cashierSubscription = null;

        try {
            $cashierSubscription = $user->newSubscription($subscriptionType, $stripePriceId)
                ->trialDays(14)
                ->quantity($seatQuantity)
                ->create($paymentMethodId);

            $cashierSubscription->update([
                'account_id' => $account->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
            ]);
        } catch (\Throwable $e) {
            $this->rollbackNewWorkspaceCheckout(
                $user,
                $user->subscriptions()->where('type', $subscriptionType)->first(),
                $account
            );

            throw $e;
        }

        try {
            $this->provisionTenantForPendingAccount($account, $user);
        } catch (\Throwable $e) {
            Log::error('Tenant provisioning failed after successful Stripe subscription', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->rollbackNewWorkspaceCheckout($user, $cashierSubscription, $account);

            throw $e;
        }

        Log::info('Subscription linked to account and tenant provisioned', [
            'subscription_id' => $cashierSubscription->id,
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'cashier_type' => $subscriptionType,
            'initial_quantity' => $seatQuantity,
        ]);

        WorkspaceNavCache::forgetForAccount($account);

        return redirect()->route('dashboard')
            ->with('success', 'Subscription created successfully!');
    }

    /**
     * Central account row only (no tenant, no schema, no migrations) until Stripe succeeds.
     */
    private function createPendingAccountWithoutTenant(\App\Models\User $user, string $accountName): Account
    {
        $account = Account::query()->create([
            'name' => $accountName,
            'owner_id' => $user->id,
            'tenant_id' => null,
        ]);

        $account->users()->attach($user->id, ['role' => 'owner']);

        WorkspaceNavCache::forgetForAccount($account);

        return $account;
    }

    /**
     * After Stripe subscription exists: create tenant (runs migrations via TenantCreated pipeline), domain, link account.
     */
    private function provisionTenantForPendingAccount(Account $account, \App\Models\User $user): void
    {
        if ($account->tenant_id !== null) {
            throw new Exception('Account already has a tenant.');
        }

        do {
            $subdomain = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $domainName = $subdomain.'.'.config('app.domain', 'localhost');
        } while (Domain::query()->where('domain', $domainName)->exists());

        $tenantId = (string) \Illuminate\Support\Str::uuid();

        $tenant = Tenant::query()->create(['id' => $tenantId]);

        $tenant->domains()->create([
            'domain' => $domainName,
        ]);

        sleep(1);

        $account->update([
            'tenant_id' => $tenant->id,
        ]);

        $this->copyUserToTenant($user, $tenant, throwOnFailure: true);

        WorkspaceNavCache::forgetForAccount($account->fresh());
    }

    /**
     * Undo a failed new-workspace checkout: cancel Stripe subscription, remove account and orphan tenant.
     */
    private function rollbackNewWorkspaceCheckout(\App\Models\User $user, ?CashierSubscription $subscription, Account $account): void
    {
        WorkspaceNavCache::forgetForAccount($account);

        $tenantId = $account->tenant_id;

        if ($subscription) {
            try {
                $subscription->cancelNow();
            } catch (\Throwable $e) {
                Log::warning('Checkout rollback: failed to cancel Stripe subscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $account->users()->detach();
            $account->delete();
        } catch (\Throwable $e) {
            Log::warning('Checkout rollback: failed to delete pending account', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($tenantId) {
            try {
                Tenant::query()->whereKey($tenantId)->delete();
            } catch (\Throwable $e) {
                Log::warning('Checkout rollback: failed to delete orphan tenant', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Copy the authenticated user to the tenant's users table.
     */
    private function copyUserToTenant(\App\Models\User $user, \App\Models\Tenant $tenant, bool $throwOnFailure = false): void
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
            if ($throwOnFailure) {
                throw $e;
            }
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
