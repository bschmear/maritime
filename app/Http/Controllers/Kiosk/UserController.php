<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\KioskRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('kioskRoles')
            ->latest()
            ->paginate(15);

        return Inertia::render('Kiosk/Users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Kiosk/Users/Create', [
            'roles' => KioskRole::all(),
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
}
