<?php

namespace App\Http\Controllers;

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
            ->with(['domains', 'owner'])
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
                ];
            });

        return Inertia::render('Dashboard', [
            'accounts' => $accounts,
        ]);
    }
}

