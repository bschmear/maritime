<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the user's dashboard with accounts.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get all accounts the user belongs to, with tenant and domain info
        $accounts = $user->accounts()
            ->with(['domains', 'owner', 'subscription.plan'])
            ->withCount('users')
            ->get()
            ->map(function ($account) use ($user) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'tenant_id' => $account->tenant_id,
                    'domain' => $account->domains->first()?->domain,
                    'is_owner' => $account->owner_id === $user->id,
                    'user_role' => $account->pivot->role,
                    'users_count' => $account->users_count,
                    'created_at' => $account->created_at,
                    'plan_name' => $account->subscription?->plan?->name,
                    'has_active_subscription' => $account->hasActiveSubscription(),
                ];
            });

        // Get pending invitations for this user
        $pendingInvitations = Invitation::with(['account.owner', 'inviter'])
            ->where('email', $user->email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'token' => $invitation->token,
                    'role' => $invitation->role,
                    'account' => [
                        'id' => $invitation->account->id,
                        'name' => $invitation->account->name,
                        'owner' => $invitation->account->owner->name,
                    ],
                    'inviter' => $invitation->inviter ? [
                        'name' => $invitation->inviter->name,
                        'email' => $invitation->inviter->email,
                    ] : null,
                    'created_at' => $invitation->created_at,
                    'invitation_url' => $invitation->getInvitationUrl(),
                ];
            });

        return Inertia::render('Dashboard', [
            'accounts' => $accounts,
            'pending_invitations' => $pendingInvitations,
        ]);
    }
}

