<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Account;
use App\Models\Plan;
use App\Mail\AccountInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Inertia\Inertia;

class InvitationController extends Controller
{
    /**
     * Show the invitation page.
     */
    public function show(Request $request, string $token)
    {
        $invitation = Invitation::with(['account.owner', 'inviter'])
            ->where('token', $token)
            ->first();

        if (!$invitation) {
            abort(404, 'Invitation not found or has expired.');
        }

        if (!$invitation->isPending()) {
            return Inertia::render('Invitation/Expired', [
                'invitation' => $invitation,
                'accepted' => $invitation->isAccepted(),
                'declined' => $invitation->isDeclined(),
            ]);
        }

        $user = Auth::user();

        // If user is not logged in, redirect to login with invitation token
        if (!$user) {
            return redirect()->route('login', ['invitation' => $token]);
        }

        // Check if user email matches invitation email
        if (strtolower($user->email) !== strtolower($invitation->email)) {
            Auth::logout();
            return redirect()->route('login', ['invitation' => $token])
                ->withErrors(['email' => 'Please log in with the email address that was invited.']);
        }

        // Check if user is already a member of the account
        if ($invitation->account->users()->where('users.id', $user->id)->exists()) {
            return Inertia::render('Invitation/AlreadyMember', [
                'invitation' => $invitation,
                'account' => $invitation->account,
            ]);
        }

        return Inertia::render('Invitation/Show', [
            'invitation' => $invitation,
            'account' => $invitation->account,
            'user' => $user,
        ]);
    }

    /**
     * Accept the invitation.
     */
public function accept(Request $request, string $token)
{
    $invitation = Invitation::with('account.subscription.plan')
        ->where('token', $token)
        ->first();

    if (!$invitation || !$invitation->isPending()) {
        return redirect()->back()->withErrors(['invitation' => 'Invitation not found or no longer valid.']);
    }

    $user = Auth::user();

    if (!$user || strtolower($user->email) !== strtolower($invitation->email)) {
        return redirect()->route('login', ['invitation' => $token])
            ->withErrors(['auth' => 'Please log in with the invited email address.']);
    }

    // Already a member?
    if ($invitation->account->users()->where('users.id', $user->id)->exists()) {
        return redirect()->back()->withErrors(['membership' => 'You are already a member of this account.']);
    }

    try {
        // Accept the invitation in your system
        $invitation->accept($user);

        $subscription = $invitation->account->subscription;

        if ($subscription && $subscription->isActive()) {

            $account = $invitation->account;
            $owner = $account->owner;
            $cashierSub = $owner->subscription('default');

            if ($cashierSub && $cashierSub->active()) {

                // Stripe client
                $stripe = new \Stripe\StripeClient(config('cashier.secret'));

                // Count total users
                $totalUsers = $account->users()->count();

                $includedSeats = $subscription->plan->seat_limit;
                $extraSeatCount = max(0, $totalUsers - $includedSeats);

                // Determine correct extra-seat price ID
                $extraSeatPriceId = $subscription->billing_cycle === 'yearly'
                    ? config('app.extra_seats.yearly_price_id')
                    : config('app.extra_seats.monthly_price_id');

                // Fetch Stripe subscription
                $stripeSub = $cashierSub->asStripeSubscription();

                // Find existing extra-seat line item
                $extraSeatItem = collect($stripeSub->items->data)
                    ->firstWhere('price.id', $extraSeatPriceId);

                if ($extraSeatCount > 0) {

                    if ($extraSeatItem) {

                        // ✔ Update quantity of existing add-on
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

                    } else {

                        // ✔ Add new extra seat price item
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
                    }

                    Log::info('Updated extra seat quantities', [
                        'account_id' => $account->id,
                        'subscription_id' => $stripeSub->id,
                        'extra_seat_price' => $extraSeatPriceId,
                        'extra_seat_qty' => $extraSeatCount,
                    ]);

                } else {
                    // NO extra seats needed — remove if exists
                    if ($extraSeatItem) {
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

                        Log::info('Removed extra seat line item (no longer needed)', [
                            'account_id' => $account->id,
                            'subscription_id' => $stripeSub->id,
                        ]);
                    }
                }

                // Update internal subscription quantity to reflect total users
                $subscription->update([
                    'quantity' => $totalUsers
                ]);
            }
        }

        // Notify account owner
        try {
            $invitation->account->owner
                ->notify(new \App\Notifications\UserJoinedAccount($user, $invitation->account, $invitation->role));
        } catch (\Exception $e) {
            Log::error('Failed to send owner notification', [
                'account_id' => $invitation->account->id,
                'error' => $e->getMessage(),
            ]);
        }

        $invitation->delete();
        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$invitation->account->name}! You have successfully joined the account as a {$invitation->role}.");

    } catch (\Exception $e) {
        Log::error('Failed to accept invitation', [
            'invitation_id' => $invitation->id,
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);

        return redirect()->back()->withErrors(['accept' => 'Failed to accept invitation. Please try again.']);
    }
}

    
    /**
     * Decline the invitation.
     */
    public function decline(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->first();

        if (!$invitation || !$invitation->isPending()) {
            return redirect()->back()->withErrors(['invitation' => 'Invitation not found or no longer valid.']);
        }

        $user = Auth::user();

        if (!$user || strtolower($user->email) !== strtolower($invitation->email)) {
            return redirect()->back()->withErrors(['auth' => 'Please log in with the invited email address.']);
        }

        try {
            $invitation->decline();

            return redirect()->route('dashboard')
                ->with('info', 'Invitation declined.');

        } catch (\Exception $e) {
            \Log::error('Failed to decline invitation', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['decline' => 'Failed to decline invitation. Please try again.']);
        }
    }

    /**
     * Resend an invitation email.
     */
    public function resend(Request $request, Invitation $invitation)
    {
        // Check if user owns the account
        if (!$this->userOwnsAccount($invitation->account)) {
            abort(403, 'You do not have permission to manage this invitation.');
        }

        if (!$invitation->isPending()) {
            return redirect()->back()->withErrors(['invitation' => 'This invitation has already been processed.']);
        }

        try {
            // Send the invitation email again
            Mail::to($invitation->email)->send(new AccountInvitation($invitation, $invitation->account, Auth::user()));

            return redirect()->back()->with('success', 'Invitation resent successfully!');
        } catch (\Exception $e) {
            \Log::error('Failed to resend invitation', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['email' => 'Failed to resend invitation. Please try again.']);
        }
    }

    /**
     * Delete an invitation.
     */
    public function destroy(Request $request, Invitation $invitation)
    {
        // Check if user owns the account
        if (!$this->userOwnsAccount($invitation->account)) {
            abort(403, 'You do not have permission to manage this invitation.');
        }

        try {
            $invitation->delete();

            return response()->json(['message' => 'Invitation deleted successfully.']);
        } catch (\Exception $e) {
            \Log::error('Failed to delete invitation', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to delete invitation.'], 500);
        }
    }

    /**
     * Check if the authenticated user owns the account.
     */
    private function userOwnsAccount(Account $account): bool
    {
        return $account->owner_id === Auth::id();
    }
}
