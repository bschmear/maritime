<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\KioskRole;
use App\Models\User;
use App\Support\WorkspaceAccountUserRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with(['kioskRoles', 'accounts', 'ownedAccounts'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->display_name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'kiosk_roles' => $user->kioskRoles->map(fn (KioskRole $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ]),
                'accounts' => $this->accountsForUser($user),
            ]);

        return Inertia::render('Kiosk/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(User $user): Response
    {
        $user->load(['kioskRoles', 'accounts', 'ownedAccounts', 'currentTenant.domains']);

        return Inertia::render('Kiosk/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->display_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'has_stripe_customer' => $user->hasStripeId(),
                'trial_ends_at' => $user->trial_ends_at,
                'current_tenant_id' => $user->current_tenant_id,
                'current_tenant_domain' => $user->currentTenant?->domains?->first()?->domain,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'kiosk_roles' => $user->kioskRoles->map(fn (KioskRole $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ]),
            'accounts' => $this->accountsForUser($user),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Users/Create', [
            'roles' => KioskRole::orderBy('name')->get(['id', 'name', 'slug']),
            'users' => User::query()
                ->orderBy('email')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->display_name,
                    'email' => $user->email,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:kiosk_roles,id',
        ]);

        $user = User::findOrFail($validated['user_id']);

        if ($user->kioskRoles()->where('kiosk_roles.id', $validated['role_id'])->exists()) {
            return back()->withErrors(['user_id' => 'User already has this role.']);
        }

        $user->kioskRoles()->attach($validated['role_id']);

        return redirect()->route('kiosk.users.index')
            ->with('success', 'User role assigned successfully.');
    }

    public function destroy(User $user, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:kiosk_roles,id',
        ]);

        $user->kioskRoles()->detach($validated['role_id']);

        return redirect()->route('kiosk.users.index')
            ->with('success', 'User role removed successfully.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountsForUser(User $user): array
    {
        $byId = [];

        foreach ($user->ownedAccounts as $owned) {
            $byId[$owned->id] = [
                'id' => $owned->id,
                'name' => $owned->name,
                'role' => 'owner',
                'role_label' => WorkspaceAccountUserRoles::labelForSlug('owner'),
                'is_owner' => true,
            ];
        }

        foreach ($user->accounts as $account) {
            $byId[$account->id] = [
                'id' => $account->id,
                'name' => $account->name,
                'role' => $account->pivot->role,
                'role_label' => WorkspaceAccountUserRoles::labelForSlug((string) $account->pivot->role),
                'is_owner' => $user->id === $account->owner_id,
            ];
        }

        return collect($byId)->sortBy('name')->values()->all();
    }
}
