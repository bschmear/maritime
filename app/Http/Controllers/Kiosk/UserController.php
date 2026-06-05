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
                'admin_access' => (bool) $user->admin_access,
                'is_support' => (bool) $user->is_support,
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
                'admin_access' => (bool) $user->admin_access,
                'is_support' => (bool) $user->is_support,
            ],
            'kiosk_roles' => $user->kioskRoles->map(fn (KioskRole $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ]),
            'accounts' => $this->accountsForUser($user),
            'all_roles' => KioskRole::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'available_roles' => KioskRole::query()
                ->orderBy('name')
                ->whereNotIn('id', $user->kioskRoles->pluck('id'))
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'admin_access' => 'required|boolean',
            'is_support' => 'required|boolean',
            'role_id' => 'nullable|exists:kiosk_roles,id',
        ]);

        $adminAccess = (bool) $validated['admin_access'];
        $roleId = $validated['role_id'] ?? null;

        if ($adminAccess && $user->kioskRoles()->count() === 0 && $roleId === null) {
            return back()->withErrors([
                'role_id' => 'Select a kiosk role when enabling admin access.',
            ]);
        }

        $user->forceFill([
            'admin_access' => $adminAccess,
            'is_support' => $adminAccess ? (bool) $validated['is_support'] : false,
        ]);

        if (! $adminAccess) {
            $user->kioskRoles()->detach();
        } elseif ($roleId !== null && ! $user->kioskRoles()->where('kiosk_roles.id', $roleId)->exists()) {
            $user->kioskRoles()->attach($roleId);
        }

        $user->save();

        return back()->with('success', 'Kiosk access updated.');
    }

    public function attachRole(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->admin_access, 403);

        $validated = $request->validate([
            'role_id' => 'required|exists:kiosk_roles,id',
        ]);

        if ($user->kioskRoles()->where('kiosk_roles.id', $validated['role_id'])->exists()) {
            return back()->withErrors(['role_id' => 'User already has this role.']);
        }

        $user->kioskRoles()->attach($validated['role_id']);

        return back()->with('success', 'Kiosk role assigned.');
    }

    public function removeKioskAccess(User $user): RedirectResponse
    {
        $user->kioskRoles()->detach();
        $user->forceFill([
            'admin_access' => false,
            'is_support' => false,
        ])->save();

        return redirect()->route('kiosk.users.index')
            ->with('success', 'User removed from kiosk.');
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

        $user->forceFill(['admin_access' => true])->save();
        $user->kioskRoles()->attach($validated['role_id']);

        return redirect()->route('kiosk.users.show', $user)
            ->with('success', 'User role assigned successfully.');
    }

    public function destroy(User $user, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:kiosk_roles,id',
        ]);

        $user->kioskRoles()->detach($validated['role_id']);

        return back()->with('success', 'User role removed successfully.');
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
