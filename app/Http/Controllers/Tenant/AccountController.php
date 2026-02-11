<?php
namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Enums\Timezone;
use App\Models\AccountSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    /**
     * Display the account management landing page.
     */
    public function index(Request $request)
    {
        // Get current tenant's account settings (cached)
        $account = AccountSettings::getCurrent();

        $accountSections = [
            [
                'title' => 'Users',
                'description' => 'Manage team members and user accounts. Add, edit, and assign roles to users in your organization.',
                'icon' => 'people',
                'href' => route('users.index'),
                'stats' => null,
            ],
            [
                'title' => 'Roles',
                'description' => 'Define and manage user roles and permissions. Control what each team member can access and do.',
                'icon' => 'shield',
                'href' => route('roles.index'),
                'stats' => null,
            ],
            [
                'title' => 'Subsidiaries',
                'description' => 'Manage your subsidiary companies and organizational structure. Track and organize related entities.',
                'icon' => 'corporate_fare',
                'href' => route('subsidiaries.index'),
                'stats' => null,
            ],
            [
                'title' => 'Locations',
                'description' => 'Manage physical locations and addresses. Add, edit, and organize your business locations.',
                'icon' => 'location_on',
                'href' => route('locations.index'),
                'stats' => null,
            ],
        ];

        return Inertia::render('Tenant/Account/Index', [
            'accountSections' => $accountSections,
            'account' => $account,
            'timezones' => Timezone::options(),
        ]);
    }

    /**
     * Update account settings.
     */
    public function update(Request $request, PublicStorage $publicStorage)
    {
        $validated = $request->validate([
            'logo' => 'nullable|image|max:2048',
            'default_timezone' => 'required|string',
            'brand_color' => 'nullable|string|max:7',
        ]);

        $account = AccountSettings::getCurrent();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');

            // Upload new logo (store method handles deleting old file if it exists)
            $result = $publicStorage->store($file, 'logos', null, $account->logo_file);

            // Update logo fields
            $account->logo_file = $result['key'];
            $account->logo_file_extension = $result['file_extension'];
            $account->logo_file_size = $result['file_size'];
        }

        // Update settings
        $account->timezone = $validated['default_timezone'];
        $account->brand_color = $validated['brand_color'] ?? $account->brand_color;
        $account->save();

        return back()->with('success', 'Account settings updated successfully.');
    }
}
