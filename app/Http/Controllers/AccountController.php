<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountInvitation;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    /**
     * Display account details and management.
     */
    public function show(Request $request, Account $account)
    {
        // Ensure user has access to this account
        if (!$this->userHasAccess($account)) {
            abort(403, 'You do not have access to this account.');
        }

        $user = Auth::user();

        // Get account with relationships (including subscription, plan, and invitations)
        $account->load([
            'owner',
            'users',
            'tenant.domains',
            'subscription.plan',
            'pendingInvitations.inviter'
        ]);

        // Get current plan
        $currentPlan = $account->currentPlan();

        // Calculate seat usage
        $seatUsage = [
            'current_users' => $account->users->count(),
            'seat_limit' => $currentPlan?->seat_limit ?? 1,
            'available_seats' => $account->withinSeatLimit() ? ($currentPlan?->seat_limit ?? 1) - $account->users->count() : 0,
            'over_limit' => $account->seatsOverLimit(),
            'additional_cost' => $account->additionalSeatCost(),
        ];

        // Get all available plans
        $plans = Plan::active()->orderBy('monthly_price')->get();

        return Inertia::render('Account/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'owner' => $account->owner,
                'tenant_id' => $account->tenant_id,
                'domain' => $account->tenant?->domains?->first()?->domain,
                'is_owner' => $account->owner_id === $user->id,
                'user_role' => $account->users()->where('users.id', $user->id)->first()?->pivot?->role,
                'created_at' => $account->created_at,
            ],
            'users' => $account->users->map(function ($user) use ($account) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'is_owner' => $account->owner_id === $user->id,
                    'created_at' => $user->pivot->created_at,
                ];
            }),
            'pending_invitations' => $account->pendingInvitations->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'invited_by' => $invitation->inviter ? [
                        'name' => $invitation->inviter->name,
                        'email' => $invitation->inviter->email,
                    ] : null,
                    'created_at' => $invitation->created_at,
                ];
            }),
            'current_plan' => $currentPlan,
            'seat_usage' => $seatUsage,
            'plans' => $plans,
            'additional_seat_cost' => 15.00, // $15 per additional user
            'current_user' => $user,
        ]);
    }

    /**
     * Invite a user to the account.
     */
    public function inviteUser(Request $request, Account $account)
    {
        if (!$this->userIsOwner($account)) {
            abort(403, 'Only account owners can manage users.');
        }

        $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|in:admin,member',
        ]);

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
            \Log::error('Failed to send invitation email', [
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
        if (!$this->userIsOwner($account)) {
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
                $cashierSub = $owner->subscription('default');

                if ($cashierSub && $cashierSub->active()) {

                    // Stripe client
                    $stripe = new \Stripe\StripeClient(config('cashier.secret'));

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
                                            'quantity' => $extraSeatCount,
                                        ],
                                    ],
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
                                        'quantity' => $extraSeatCount,
                                    ],
                                ],
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
        if (!$this->userIsOwner($account)) {
            abort(403, 'Only account owners can manage user roles.');
        }

        // Cannot change owner role
        if ($account->owner_id === $user->id) {
            return redirect()->back()->withErrors(['user' => 'Cannot change account owner role.']);
        }

        $request->validate([
            'role' => 'required|in:admin,member',
        ]);

        $account->users()->updateExistingPivot($user->id, ['role' => $request->role]);

        return redirect()->back()->with('success', 'User role updated successfully.');
    }

    /**
     * Switch account plan.
     *
     * This method updates both the local subscription record and Stripe subscription.
     * Important: Make sure Stripe webhooks are configured to keep subscription data in sync.
     */
    public function switchPlan(Request $request, Account $account)
    {
        if (!$this->userIsOwner($account)) {
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
        $cashierSubscription = $owner->subscription('default');
    
        if (!$cashierSubscription || !$cashierSubscription->active()) {
            return redirect()->back()->withErrors(['subscription' => 'No active subscription found.']);
        }
    
        try {
            // Swap base plan in Stripe
            $newPriceId = $plan->getStripePriceId($billingCycle);
            if (!$newPriceId) {
                return redirect()->back()->withErrors(['plan' => 'Stripe price ID not configured for this plan and billing cycle.']);
            }
    
            $cashierSubscription->swap($newPriceId);
    
            // Recalculate extra seats with new plan
            $totalUsers = $account->users()->count();
            $includedSeats = $plan->seat_limit;
            $extraSeats = max(0, $totalUsers - $includedSeats);

            // Extra seat Stripe price ID
            $extraSeatPriceId = $billingCycle === 'yearly'
                ? config('app.extra_seats.yearly_price_id')
                : config('app.extra_seats.monthly_price_id');

            if ($extraSeatPriceId) {
                // Use Stripe SDK directly for reliable updates
                $stripe = new \Stripe\StripeClient(config('cashier.secret'));
                $stripeSub = $cashierSubscription->asStripeSubscription();
                $extraSeatItem = collect($stripeSub->items->data)
                    ->firstWhere('price.id', $extraSeatPriceId);

                if ($extraSeatItem) {
                    if ($extraSeats > 0) {
                        // Update quantity
                        $stripe->subscriptions->update(
                            $stripeSub->id,
                            [
                                'items' => [
                                    [
                                        'id' => $extraSeatItem->id,
                                        'quantity' => $extraSeats,
                                    ],
                                ],
                            ]
                        );

                        \Log::info('Updated extra seat quantity after plan switch', [
                            'account_id' => $account->id,
                            'subscription_id' => $stripeSub->id,
                            'new_plan_id' => $plan->id,
                            'extra_seats' => $extraSeats,
                            'billing_cycle' => $billingCycle,
                        ]);
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
                            ]
                        );

                        \Log::info('Removed extra seat line item after plan switch', [
                            'account_id' => $account->id,
                            'subscription_id' => $stripeSub->id,
                            'new_plan_id' => $plan->id,
                            'billing_cycle' => $billingCycle,
                        ]);
                    }

                } elseif ($extraSeats > 0) {
                    // Need extra seats but item doesn't exist - add it
                    $stripe->subscriptions->update(
                        $stripeSub->id,
                        [
                            'items' => [
                                [
                                    'price' => $extraSeatPriceId,
                                    'quantity' => $extraSeats,
                                ],
                            ],
                        ]
                    );

                    \Log::info('Added extra seat line item after plan switch', [
                        'account_id' => $account->id,
                        'subscription_id' => $stripeSub->id,
                        'new_plan_id' => $plan->id,
                        'extra_seats' => $extraSeats,
                        'billing_cycle' => $billingCycle,
                    ]);
                }
            }
    
            // Update internal subscription record
            $subscription = $account->subscription;
            if ($subscription) {
                $subscription->update([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'quantity' => $totalUsers,
                ]);
            }
    
        } catch (\Laravel\Cashier\Exceptions\SubscriptionUpdateFailure $e) {
            \Log::error('Stripe subscription update failed', [
                'account_id' => $account->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['stripe' => 'Failed to update subscription with Stripe. Please check your payment method and try again.']);
        } catch (\Exception $e) {
            \Log::error('Plan switch failed', [
                'account_id' => $account->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['stripe' => 'Failed to update subscription. Please try again or contact support.']);
        }

        return redirect()->back()->with('success', 'Plan updated successfully! Your billing and extra seats have been adjusted.');
    }

    /**
     * Cancel account subscription.
     */
    public function cancelSubscription(Request $request, Account $account)
    {
        if (!$this->userIsOwner($account)) {
            abort(403, 'Only account owners can cancel subscriptions.');
        }

        $subscription = $account->subscription;

        if (!$subscription || !$subscription->isActive()) {
            return redirect()->back()->withErrors(['subscription' => 'No active subscription found.']);
        }

        try {
            $owner = $account->owner;
            $cashierSubscription = $owner->subscription('default');

            if (!$cashierSubscription || !$cashierSubscription->active()) {
                return redirect()->back()->withErrors(['subscription' => 'No active subscription found.']);
            }

            // Cancel the subscription at period end (not immediately)
            $cashierSubscription->cancel();

            \Log::info('Subscription cancelled', [
                'account_id' => $account->id,
                'subscription_id' => $cashierSubscription->stripe_id,
                'ends_at' => $cashierSubscription->ends_at,
            ]);

            return redirect()->back()->with('success', 'Subscription cancelled successfully. You will retain access until ' . $cashierSubscription->ends_at->format('F j, Y') . '.');

        } catch (\Exception $e) {
            \Log::error('Subscription cancellation failed', [
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
        $subscription = $account->subscription;
        if (!$subscription || !$subscription->isActive()) {
            return;
        }
    
        $owner = $account->owner;
        $cashierSub = $owner->subscription('default');
    
        if (!$cashierSub || !$cashierSub->active()) {
            \Log::warning('No active Cashier subscription for account', ['account_id' => $account->id]);
            return;
        }
    
        $totalUsers = $account->users()->count();
        $plan = $account->currentPlan();
        $includedSeats = $plan ? $plan->seat_limit : 1;
        $extraSeats = max(0, $totalUsers - $includedSeats);

        $extraSeatPriceId = $subscription->billing_cycle === 'yearly'
            ? config('app.extra_seats.yearly_price_id')
            : config('app.extra_seats.monthly_price_id');

        if (!$extraSeatPriceId) {
            \Log::warning('Extra seat price ID not configured', [
                'account_id' => $account->id,
                'billing_cycle' => $subscription->billing_cycle,
            ]);
            return;
        }

        // Use Stripe SDK directly
        $stripe = new \Stripe\StripeClient(config('cashier.secret'));
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
                                    'quantity' => $extraSeats,
                                ],
                            ],
                        ]
                    );

                    \Log::info('Synced extra seat quantity in Stripe', [
                        'account_id' => $account->id,
                        'subscription_id' => $stripeSub->id,
                        'extra_seats' => $extraSeats,
                    ]);
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
                        ]
                    );

                    \Log::info('Removed extra seat line item', [
                        'account_id' => $account->id,
                        'subscription_id' => $stripeSub->id,
                    ]);
                }

            } elseif ($extraSeats > 0) {
                // Need extra seats but item doesn't exist - add it
                $stripe->subscriptions->update(
                    $stripeSub->id,
                    [
                        'items' => [
                            [
                                'price' => $extraSeatPriceId,
                                'quantity' => $extraSeats,
                            ],
                        ],
                    ]
                );

                \Log::info('Added extra seat line item', [
                    'account_id' => $account->id,
                    'subscription_id' => $stripeSub->id,
                    'extra_seats' => $extraSeats,
                ]);
            }

            // Always update internal subscription quantity
            $subscription->update(['quantity' => $totalUsers]);

        } catch (\Exception $e) {
            \Log::error('Failed to sync extra seats', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

}
