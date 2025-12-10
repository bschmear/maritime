<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    /**
     * Display the account management landing page.
     */
    public function index(Request $request)
    {
        
        $accountSections = [
            [
                'title' => 'Users',
                'description' => 'Manage team members and user accounts. Add, edit, and assign roles to users in your organization.',
                'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                'href' => route('users.index'),
                'color' => 'blue',
                'stats' => null, // Could add user count later
            ],
            [
                'title' => 'Roles',
                'description' => 'Define and manage user roles and permissions. Control what each team member can access and do.',
                'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                'href' => route('roles.index'),
                'color' => 'green',
                'stats' => null, // Could add role count later
            ],
        ];
        // dd($accountSections);
        return Inertia::render('Tenant/Account/Index', [
            'accountSections' => $accountSections,
        ]);
    }

}
