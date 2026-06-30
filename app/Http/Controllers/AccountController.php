<?php

namespace App\Http\Controllers;

use App\Domain\Role\Models\Role;
use App\Mail\AccountInvitation;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\WorkspaceAccountUserRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Stripe\StripeClient;

class AccountController extends Controller
{
    /**
     * Display account details and management.
     */
    public function show(Request $request, Account $account)
    {
        // Ensure user has access to this account
        if (! $this->userHasAccess($account)) {
            abort(403, 'You do not have access to this account.');
        }

        $user = Auth::user();

        // Get account with relationships (including subscription, plan, and invitations)
        $account->load([
            'owner',
            'users',
            'tenant.domains',
            'subscription.plan',
            'pendingInvitations.inviter',
        ]);

        // Current plan + billing cycle come from the owner's Cashier subscription (same source as switch/cancel).
        $currentPlan = $account->currentPlan();
        $hasActiveSubscription = $account->hasActiveSubscription();
        if ($currentPlan) {
            $cashierSub = $account->owner?->cashierSubscriptionForAccount($account);
            if ($cashierSub) {
                $currentPlan->billing_cycle = $cashierSub->billing_cycle;
            }
        }

        // Calculate seat usage
        $seatUsage = $account->seatUsageForDisplay();

        $tenantRoleRows = $this->tenantRolesForAccountWorkspace($account);
        $roleDisplayBySlug = collect($tenantRoleRows)->mapWithKeys(fn (array $row) => [
            $row['slug'] => $row['display_name'],
        ])->all();

        // Get all available plans
        $plans = Plan::active()->orderBy('monthly_price')->get();

        $isOwner = $account->owner_id === $user->id;
        $billingOwner = $account->owner;

        return Inertia::render('Account/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'owner' => $account->owner,
                'tenant_id' => $account->tenant_id,
                'domain' => $account->tenant?->domains?->first()?->domain,
                'is_owner' => $isOwner,
                'user_role' => $account->users()->where('users.id', $user->id)->first()?->pivot?->role,
                'created_at' => $account->created_at,
            ],
            'billing' => [
                'can_manage' => $isOwner,
                'has_stripe_customer' => (bool) $billingOwner?->hasStripeId(),
                'payment_method' => $billingOwner && $billingOwner->hasDefaultPaymentMethod() ? [
                    'type' => $billingOwner->pm_type,
                    'last_four' => $billingOwner->pm_last_four,
                ] : null,
                'stripe_key' => $isOwner ? config('cashier.key') : null,
            ],
            'users' => $account->users->map(function ($user) use ($account) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_owner' => $account->owner_id === $user->id,
                    'created_at' => $user->pivot->created_at,
                ];
            }),
            'pending_invitations' => $account->pendingInvitations->map(function ($invitation) use ($roleDisplayBySlug) {
                $slug = (string) $invitation->role;

                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role_display_name' => $roleDisplayBySlug[$slug] ?? WorkspaceAccountUserRoles::labelForSlug($slug),
                    'invited_by' => $invitation->inviter ? [
                        'name' => $invitation->inviter->name,
                        'email' => $invitation->inviter->email,
                    ] : null,
                    'created_at' => $invitation->created_at,
                ];
            }),
            'current_plan' => $currentPlan,
            'has_active_subscription' => $hasActiveSubscription,
            'seat_usage' => $seatUsage,
            'plans' => $plans,
            'additional_seat_cost' => (float) (config('app.extra_seats.monthly_price') ?: 15.0),
            'current_user' => $user,
            'tenant_workspace_roles' => $tenantRoleRows,
        ]);
    }

    /**
     * Roles from the workspace (tenant) DB, used when inviting so the new member’s tenant role matches the workspace.
     *
     * @return list<array{slug: string, display_name: string, description: string|null}>
     */
    protected function tenantRolesForAccountWorkspace(Account $account): array
    {
        if (! $account->tenant_id) {
            return [];
        }

        $tenant = Tenant::find($account->tenant_id);
        if (! $tenant) {
            return [];
        }

        tenancy()->initialize($tenant);
        try {
            return Role::query()
                ->orderBy('display_name')
                ->get(['slug', 'display_name', 'description'])
                ->map(fn (Role $role) => [
                    'slug' => $role->slug,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                ])
                ->values()
                ->all();
        } finally {
            tenancy()->end();
        }
    }

    protected function tenantRoleSlugExistsOnWorkspace(Account $account, string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        if (! $account->tenant_id) {
            return false;
        }

        $tenant = Tenant::find($account->tenant_id);
        if (! $tenant) {
            return false;
        }

        tenancy()->initialize($tenant);
        try {
            return Role::query()->where('slug', $slug)->exists();
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Invite a user to the account.
     */
    public function inviteUser(Request $request, Account $account)
    {
        if (! $this->userIsOwner($account)) {
            abort(403, 'Only account owners can manage users.');
        }

        $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|string|max:64',
        ]);

        if (! $this->tenantRoleSlugExistsOnWorkspace($account, $request->role)) {
            return redirect()->back()->withErrors(['role' => 'Choose a valid workspace role from your workspace’s role list.']);
        }

        $email = strtolower(trim($request->email));

        // Check if user is already in the account
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && $account->users()->where('users.id', $existingUser->id)->exists()) {
            return redirect()->back()->withErrors(['email' => 'User is already a member of this account.']);
        }

        // Check if there's already a pending invitation
        $existingInvitation = Invitation::where('account_id', $account->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->first();

        if ($existingInvitation) {
            return redirect()->back()->withErrors(['email' => 'An invitation has already been sent to this email address.']);
        }

        // Create invitation
        $invitation = Invitation::create([
            'account_id' => $account->id,
            'user_id' => Auth::id(), // Inviter
            'role' => $request->role,
            'email' => $email,
        ]);

        // Send invitation email
        try {
            Mail::to($email)->send(new AccountInvitation($invitation, $account, Auth::user()));
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email', [
                'invitation_id' => $invitation->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the request, just log the error
        }

        return redirect()->back()->with('success', 'Invitation sent successfully!');
    }

    /**
     * Remove a user from the account.
     */
    public function removeUser(Request $request, Account $account, User $user)
    {
        if (! $this->userIsOwner($account)) {
            abort(403, 'Only account owners can manage users.');
        }

        // Cannot remove owner
        if ($account->owner_id === $user->id) {
            return redirect()->back()->withErrors(['user' => 'Cannot remove account owner.']);
        }

        // Cannot remove yourself
        if (Auth::id() === $user->id) {
            return redirect()->back()->withErrors(['user' => 'Cannot remove yourself from the account.']);
        }

        // Remove user from account
        $account->users()->detach($user->id);

        // Stripe billing logic
        $subscription = $account->subscription;

        if ($subscription && $subscription->isActive()) {
            try {
                $owner = $account->owner;
                $cashierSub = $owner->cashierSubscriptionForAccount($account);

                if ($cashierSub && $cashierSub->active()) {

                    // Stripe client
                    $stripe = new StripeClient(config('cashier.secret'));

                    // Recalculate user count AFTER removal
                    $totalUsers = $account->users()->count();

                    // Seats included in the plan
                    $includedSeats = $subscription->plan->seat_limit;

                    // Extra seat calculation
                    $extraSeatCount = max(0, $totalUsers - $includedSeats);

                    // Price ID for extra seats
                    $extraSeatPriceId = $subscription->billing_cycle === 'yearly'
                        ? config('app.extra_seats.yearly_price_id')
                        : config('app.extra_seats.monthly_price_id');

                    // Fetch Stripe subscription items
                    $stripeSub = $cashierSub->asStripeSubscription();
                    $extraSeatItem = collect($stripeSub->items->data)
                        ->firstWhere('price.id', $extraSeatPriceId);

                    if ($extraSeatItem) {
                        // Extra seat item exists - update or remove
                        if ($extraSeatCount > 0) {
                            // Update quantity to new count
                            $stripe->subscriptions->update(
                                $stripeSub->id,
                                [
                                    'items' => [
                                        [
                                            'id' => $extraSeatItem->id,
                                            'quantity' => (int) $extraSeatCount,
                                        ],
                                    ],
                                    'proration_behavior' => 'create_prorations',
                                ]
                            );

                            Log::info('Updated extra seat quantity after user removal', [
                                'account_id' => $account->id,
                                'subscription_id' => $stripeSub->id,
                                'extra_seat_price' => $extraSeatPriceId,
                                'extra_seat_qty' => $extraSeatCount,
                            ]);
                        } else {
                            // No extra seats needed - remove the line item
                            $stripe->subscriptions->update(
                                $stripeSub->id,
                                [
                                    'items' => [
                                        [
                                            'id' => $extraSeatItem->id,
                                            'deleted' => true,
                                        ],
                                    ],
                                    'proration_behavior' => 'create_prorations',
                                ]
                            );

                            Log::info('Removed extra seat line item after user removal', [
                                'account_id' => $account->id,
                                'subscription_id' => $stripeSub->id,
                            ]);
                        }
                    } elseif ($extraSeatCount > 0) {
                        // Extra seat item doesn't exist but we need it - add it
                        $stripe->subscriptions->update(
                            $stripeSub->id,
                            [
                                'items' => [
                                    [
                                        'price' => $extraSeatPriceId,
                                        'quantity' => (int) $extraSeatCount,
                                    ],
                                ],
                                'proration_behavior' => 'create_prorations',
                            ]
                        );

                        Log::info('Added extra seat line item after user removal', [
                            'account_id' => $account->id,
                            'subscription_id' => $stripeSub->id,
                            'extra_seat_price' => $extraSeatPriceId,
                            'extra_seat_qty' => $extraSeatCount,
                        ]);
                    }

                    // Update internal subscription record
                    $subscription->update([
                        'quantity' => $totalUsers,
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('Failed to handle Stripe seat removal', [
                    'account_id' => $account->id,
                    'removed_user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Don't fail the user removal, continue with success
            }
        }

        return redirect()->back()->with('success', 'User removed from account successfully.');
    }

    /**
     * Update user role in the account.
     */
    public function updateUserRole(Request $request, Account $account, User $user)
    {
        abort(403, 'Workspace member roles can only be changed from the workspace by an administrator (Users → Edit).');
    }

    /**
     * Switch account plan.
     *
     * This method updates both the local subscription record and Stripe subscription.
     * Important: Make sure Stripe webhooks are configured to keep subscription data in sync.
     */
    public function switchPlan(Request $request, Account $account)
    {
        if (! $this->userIsOwner($account)) {
            abort(403, 'Only account owners can change plans.');
        }

        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $billingCycle = $request->billing_cycle;

        // Get the Laravel Cashier subscription (owned by account owner)
        $owner = $account->owner;
        $cashierSubscription = $owner->cashierSubscriptionForAccount($account);

        if (! $cashierSubscription || ! $cashierSubscription->active()) {
            return redirect()->back()->withErrors(['subscription' => 'No active subscription found.']);
        }

        try {
            // Get new price IDs
            $newPriceId = $plan->getStripePriceId($billingCycle);
            if (! $newPriceId) {
                return redirect()->back()->withErrors(['plan' => 'Stripe price ID not configured for this plan and billing cycle.']);
            }

            // Recalculate extra seats with new plan
            $totalUsers = $account->users()->count();
            $includedSeats = $plan->seat_limit;
            $extraSeats = max(0, $totalUsers - $includedSeats);

            // Use Stripe SDK directly for ALL updates (base plan + extra seats) in ONE atomic call
            $stripe = new StripeClient(config('cashier.secret'));
            $stripeSub = $cashierSubscription->asStripeSubscription();

            // Identify subscription items
            $monthlyExtraSeatPriceId = config('app.extra_seats.monthly_price_id');
            $yearlyExtraSeatPriceId = config('app.extra_seats.yearly_price_id');
            $newExtraSeatPriceId = $billingCycle === 'yearly' ? $yearlyExtraSeatPriceId : $monthlyExtraSeatPriceId;

            // Find the BASE PLAN item (the main subscription item)
            $basePlanItem = collect($stripeSub->items->data)->first(function ($item) use ($monthlyExtraSeatPriceId, $yearlyExtraSeatPriceId) {
                // Base plan is any item that's NOT an extra seat price
                return $item->price->id !== $monthlyExtraSeatPriceId && $item->price->id !== $yearlyExtraSeatPriceId;
            });

            // Find ALL extra seat line items (both monthly and yearly)
            $extraSeatItems = collect($stripeSub->items->data)->filter(function ($item) use ($monthlyExtraSeatPriceId, $yearlyExtraSeatPriceId) {
                return $item->price->id === $monthlyExtraSeatPriceId || $item->price->id === $yearlyExtraSeatPriceId;
            });

            // Build array of ALL subscription item changes (base plan + extra seats)
            $itemsToUpdate = [];

            // Update the base plan item to the new price
            if ($basePlanItem) {
                $itemsToUpdate[] = [
                    'id' => $basePlanItem->id,
                    'price' => $newPriceId, // Change to new plan price
                ];
            }

            // Mark all existing extra seat items for deletion
            foreach ($extraSeatItems as $extraSeatItem) {
                $itemsToUpdate[] = [
                    'id' => $extraSeatItem->id,
                    'deleted' => true,
                ];
            }

            // Add new extra seat line item if needed
            if ($extraSeats > 0 && $newExtraSeatPriceId) {
                $itemsToUpdate[] = [
                    'price' => $newExtraSeatPriceId,
                    'quantity' => (int) $extraSeats,
                ];
            }

            // Apply ALL changes (base plan swap + extra seat adjustments) in a SINGLE atomic API call
            $stripe->subscriptions->update(
                $stripeSub->id,
                [
                    'items' => $itemsToUpdate,
                    'proration_behavior' => 'create_prorations',
                ]
            );

            Log::info('Switched plan and updated subscription items atomically', [
                'account_id' => $account->id,
                'subscription_id' => $stripeSub->id,
                'old_plan_id' => $cashierSubscription->plan_id,
                'new_plan_id' => $plan->id,
                'extra_seats' => $extraSeats,
                'billing_cycle' => $billingCycle,
                'total_items_updated' => count($itemsToUpdate),
            ]);

            // Update internal subscription row (same id as Cashier model)
            $subscription = Subscription::query()->find($cashierSubscription->getKey());
            if ($subscription) {
                $subscription->update([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'quantity' => $totalUsers,
                ]);
            }

        } catch (SubscriptionUpdateFailure $e) {
            Log::error('Stripe subscription update failed', [
                'account_id' => $account->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['stripe' => 'Failed to update subscription with Stripe. Please check your payment method and try again.']);
        } catch (\Exception $e) {
            Log::error('Plan switch failed', [
                'account_id' => $account->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'error' => $e->getMessage(),
            ]);

            if (str_contains($e->getMessage(), 'No such subscription')) {
                try {
                    $cashierSubscription->delete();
                } catch (\Exception $deleteException) {
                    Log::warning('Failed to delete stale subscription row', [
                        'account_id' => $account->id,
                        'error' => $deleteException->getMessage(),
                    ]);
                }

                return redirect()->back()->withErrors([
                    'subscription' => 'This workspace was linked to a subscription that no longer exists in Stripe. Choose a plan on Pricing to subscribe again.',
                ]);
            }

            return redirect()->back()->withErrors(['stripe' => 'Failed to update subscription. Please try again or contact support.']);
        }

        return redirect()->back()->with('success', 'Plan updated successfully! Your billing and extra seats have been adjusted.');
    }

    /**
     * Cancel account subscription.
     */
    public function cancelSubscription(Request $request, Account $account)
    {
        if (! $this->userIsOwner($account)) {
            abort(403, 'Only account owners can cancel subscriptions.');
        }

        try {
            $owner = $account->owner;
            $cashierSubscription = $owner->cashierSubscriptionForAccount($account);

            if (! $cashierSubscription || ! $cashierSubscription->active()) {
                return redirect()->back()->withErrors(['subscription' => 'No active subscription found.']);
            }

            // Cancel the subscription at period end (not immediately)
            $cashierSubscription->cancel();

            return redirect()->back()->with('success', 'Subscription cancelled successfully. You will retain access until '.$cashierSubscription->ends_at->format('F j, Y').'.');

        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['stripe' => 'Failed to cancel subscription. Please try again or contact support.']);
        }
    }

    /**
     * Check if user has access to account.
     */
    private function userHasAccess(Account $account): bool
    {
        $user = Auth::user();

        return $account->users()->where('users.id', $user->id)->exists() ||
               $account->owner_id === $user->id;
    }

    /**
     * Check if user is the owner of the account.
     */
    private function userIsOwner(Account $account): bool
    {
        return $account->owner_id === Auth::id();
    }

    private function syncExtraSeats(Account $account)
    {
        $owner = $account->owner;
        $cashierSub = $owner?->cashierSubscriptionForAccount($account);

        if (! $cashierSub || ! $cashierSub->active()) {
            return;
        }

        $localSubscription = Subscription::query()->find($cashierSub->getKey());

        $totalUsers = $account->users()->count();
        $plan = $account->currentPlan();
        $includedSeats = $plan ? $plan->seat_limit : 1;
        $extraSeats = max(0, $totalUsers - $includedSeats);

        $extraSeatPriceId = $cashierSub->billing_cycle === 'yearly'
            ? config('app.extra_seats.yearly_price_id')
            : config('app.extra_seats.monthly_price_id');

        if (! $extraSeatPriceId) {
            Log::warning('Extra seat price ID not configured', [
                'account_id' => $account->id,
                'billing_cycle' => $cashierSub->billing_cycle,
            ]);

            return;
        }

        // Use Stripe SDK directly
        $stripe = new StripeClient(config('cashier.secret'));
        $stripeSub = $cashierSub->asStripeSubscription();
        $extraSeatItem = collect($stripeSub->items->data)
            ->firstWhere('price.id', $extraSeatPriceId);

        try {
            if ($extraSeatItem) {
                if ($extraSeats > 0) {
                    // Update quantity
                    $stripe->subscriptions->update(
                        $stripeSub->id,
                        [
                            'items' => [
                                [
                                    'id' => $extraSeatItem->id,
                                    'quantity' => (int) $extraSeats,
                                ],
                            ],
                            'proration_behavior' => 'create_prorations',
                        ]
                    );

                } else {
                    // Remove extra seat line item
                    $stripe->subscriptions->update(
                        $stripeSub->id,
                        [
                            'items' => [
                                [
                                    'id' => $extraSeatItem->id,
                                    'deleted' => true,
                                ],
                            ],
                            'proration_behavior' => 'create_prorations',
                        ]
                    );

                }

            } elseif ($extraSeats > 0) {
                // Need extra seats but item doesn't exist - add it
                $stripe->subscriptions->update(
                    $stripeSub->id,
                    [
                        'items' => [
                            [
                                'price' => $extraSeatPriceId,
                                'quantity' => (int) $extraSeats,
                            ],
                        ],
                        'proration_behavior' => 'create_prorations',
                    ]
                );

            }

            // Always update internal subscription quantity
            if ($localSubscription) {
                $localSubscription->update(['quantity' => $totalUsers]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync extra seats', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
