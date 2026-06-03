<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Subscription;
use App\Support\WorkspaceAccountUserRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Invoice;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $accounts = Account::query()
            ->with(['owner:id,name,email', 'tenant.domains', 'subscription.plan'])
            ->withCount('users')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhereHas('owner', fn ($owner) => $owner
                            ->where('email', 'ilike', "%{$search}%")
                            ->orWhere('name', 'ilike', "%{$search}%"))
                        ->orWhereHas('tenant.domains', fn ($domain) => $domain
                            ->where('domain', 'ilike', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Account $account) => $this->formatAccountSummary($account));

        return Inertia::render('Kiosk/Accounts/Index', [
            'accounts' => $accounts,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Account $account): Response
    {
        $account->load([
            'owner:id,name,email',
            'users',
            'tenant.domains',
            'subscription.plan',
        ]);

        $cashierSub = $account->owner?->cashierSubscriptionForAccount($account);
        $currentPlan = $account->currentPlan();
        if ($currentPlan && $cashierSub) {
            $currentPlan->billing_cycle = $cashierSub->billing_cycle;
        }

        return Inertia::render('Kiosk/Accounts/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'created_at' => $account->created_at,
                'owner' => $account->owner ? [
                    'id' => $account->owner->id,
                    'name' => $account->owner->display_name,
                    'email' => $account->owner->email,
                ] : null,
                'tenant_id' => $account->tenant_id,
                'domain' => $account->tenant?->domains?->first()?->domain,
            ],
            'subscription' => $this->formatSubscriptionStatus($account, $cashierSub, $currentPlan),
            'seat_usage' => $account->seatUsageForDisplay(),
            'users' => $this->usersForAccountDisplay($account),
            'payment_history' => $this->paymentHistoryForAccount($account, $cashierSub),
            'subscription_history' => Subscription::query()
                ->where('account_id', $account->id)
                ->with('plan:id,name')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (Subscription $sub) => [
                    'id' => $sub->id,
                    'stripe_status' => $sub->stripe_status,
                    'billing_cycle' => $sub->billing_cycle,
                    'plan_name' => $sub->plan?->name,
                    'trial_ends_at' => $sub->trial_ends_at,
                    'ends_at' => $sub->ends_at,
                    'created_at' => $sub->created_at,
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAccountSummary(Account $account): array
    {
        $cashierSub = $account->owner?->cashierSubscriptionForAccount($account);
        $localSub = $account->subscription;
        $stripeStatus = $cashierSub?->stripe_status ?? $localSub?->stripe_status;

        return [
            'id' => $account->id,
            'name' => $account->name,
            'owner' => $account->owner ? [
                'id' => $account->owner->id,
                'name' => $account->owner->display_name,
                'email' => $account->owner->email,
            ] : null,
            'domain' => $account->tenant?->domains?->first()?->domain,
            'users_count' => $account->users_count,
            'has_active_subscription' => $account->hasActiveSubscription(),
            'subscription_status' => $stripeStatus ?? 'none',
            'plan_name' => $account->currentPlan()?->name ?? $localSub?->plan?->name,
            'created_at' => $account->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSubscriptionStatus(Account $account, $cashierSub, $currentPlan): array
    {
        return [
            'has_active_subscription' => $account->hasActiveSubscription(),
            'stripe_status' => $cashierSub?->stripe_status,
            'stripe_id' => $cashierSub?->stripe_id,
            'billing_cycle' => $cashierSub?->billing_cycle ?? $account->subscription?->billing_cycle,
            'trial_ends_at' => $cashierSub?->trial_ends_at ?? $account->subscription?->trial_ends_at,
            'ends_at' => $cashierSub?->ends_at ?? $account->subscription?->ends_at,
            'on_trial' => $cashierSub?->onTrial() ?? false,
            'cancelled' => $cashierSub ? $cashierSub->canceled() : false,
            'plan' => $currentPlan ? [
                'id' => $currentPlan->id,
                'name' => $currentPlan->name,
                'monthly_price' => $currentPlan->monthly_price,
                'yearly_price' => $currentPlan->yearly_price,
            ] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function usersForAccountDisplay(Account $account): array
    {
        $rows = $account->users->mapWithKeys(fn ($user) => [
            $user->id => [
                'id' => $user->id,
                'name' => $user->display_name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'role_label' => WorkspaceAccountUserRoles::labelForSlug((string) $user->pivot->role),
                'is_owner' => $account->owner_id === $user->id,
                'joined_at' => $user->pivot->created_at,
            ],
        ]);

        if ($account->owner && ! $rows->has($account->owner->id)) {
            $rows[$account->owner->id] = [
                'id' => $account->owner->id,
                'name' => $account->owner->display_name,
                'email' => $account->owner->email,
                'role' => 'owner',
                'role_label' => WorkspaceAccountUserRoles::labelForSlug('owner'),
                'is_owner' => true,
                'joined_at' => $account->created_at,
            ];
        }

        return $rows->sortBy('name')->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentHistoryForAccount(Account $account, $cashierSub): array
    {
        $owner = $account->owner;
        if (! $owner || ! $owner->hasStripeId()) {
            return [];
        }

        try {
            return collect($owner->invoices())
                ->filter(function (Invoice $invoice) use ($cashierSub) {
                    if (! $cashierSub?->stripe_id) {
                        return true;
                    }

                    return $invoice->subscriptionId() === $cashierSub->stripe_id;
                })
                ->take(24)
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'date' => $invoice->date()?->toIso8601String(),
                    'total' => $invoice->total(),
                    'status' => $invoice->status,
                    'pdf_url' => $invoice->invoice_pdf,
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Kiosk: failed to load Stripe invoices for account', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
