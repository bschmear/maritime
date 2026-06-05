<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\User\Models\User;
use App\Enums\Payments\Terms;
use App\Enums\Timezone;
use App\Models\Account;
use App\Models\AccountSettings;
use Illuminate\Http\RedirectResponse;
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
        $centralAccount = Account::query()->where('tenant_id', tenant()->id)->first();

        // Get users for the notification dropdown
        $users = User::select('id', 'display_name', 'email')
            ->orderBy('display_name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->display_name ?: $user->email,
                    'email' => $user->email,
                ];
            });

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
            [
                'title' => 'Payments',
                'description' => 'Connect Stripe to accept customer and invoice payments. Configure which payment methods you offer.',
                'icon' => 'payments',
                'href' => route('account.payments'),
                'stats' => null,
            ],
            [
                'title' => 'Consignment policy & agreements',
                'description' => 'Configure consignment fee, terms, and policy bullets shown on owner-facing consignment agreements.',
                'icon' => 'article',
                'href' => route('account.consignment.index'),
                'stats' => null,
            ],
            [
                'title' => 'Text notifications',
                'description' => 'Turn on transactional SMS alerts for your organization and choose which events send a text (not chat or marketing).',
                'icon' => 'sms',
                'href' => route('account.notifications.sms.index'),
                'stats' => null,
            ],
        ];

        return Inertia::render('Tenant/Account/Index', [
            'accountSections' => $accountSections,
            'account' => $account,
            'allow_support_access' => (bool) $centralAccount?->allow_support_access,
            'app_name' => config('app.name'),
            'timezones' => Timezone::options(),
            'users' => $users,
            'paymentTermOptions' => Terms::options(),
            'show_account_intro_modal' => $account->onboarding_complete && ! $account->account_overviewed,
        ]);
    }

    /**
     * Update account settings.
     */
    public function update(Request $request, PublicStorage $publicStorage)
    {
        $validated = $request->validate([
            'logo' => 'nullable|image|max:2048',
            'sandbox_mode' => 'required|boolean',
            'allow_support_access' => 'required|boolean',
            'default_timezone' => 'required|string',
            'brand_color' => 'nullable|string|max:7',
            'estimate_threshold_percent' => 'required|integer|min:0|max:100',
            'service_ticket_ack_text' => 'required|string|max:1000',
            'service_ticket_signed_notify_user_id' => 'nullable|exists:users,id',
            'default_contract_terms' => 'nullable|string|max:20000',
            'default_payment_term' => 'nullable|string|max:64',
            'default_payment_terms' => 'nullable|string|max:20000',
            'default_delivery_terms' => 'nullable|string|max:20000',
            'workday_hours' => 'required|integer|min:4|max:10',
            'start_time' => 'required|date_format:H:i',
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
        $account->estimate_threshold_percent = $validated['estimate_threshold_percent'];
        $account->service_ticket_ack_text = $validated['service_ticket_ack_text'];
        $account->service_ticket_signed_notify_user_id = $validated['service_ticket_signed_notify_user_id'] ?? null;
        $account->default_contract_terms = $validated['default_contract_terms'] ?? null;
        $account->default_payment_term = $validated['default_payment_term'] ?? Terms::DueOnReceipt->value;
        $account->default_payment_terms = $validated['default_payment_terms'] ?? null;
        $account->default_delivery_terms = $validated['default_delivery_terms'] ?? null;
        $account->workday_hours = $validated['workday_hours'];
        $startTime = $validated['start_time'];
        $account->start_time = strlen($startTime) === 5 ? $startTime.':00' : $startTime;
        $account->allow_overlap = $request->boolean('allow_overlap');
        $account->sandbox_mode = $validated['sandbox_mode'];

        $account->save();

        $centralAccount = Account::query()->where('tenant_id', tenant()->id)->first();
        if ($centralAccount) {
            $centralAccount->forceFill([
                'allow_support_access' => $validated['allow_support_access'],
            ])->save();
        }

        return back()->with('success', 'Account settings updated successfully.');
    }

    /**
     * Dismiss the one-time “Account overview” modal after onboarding.
     */
    public function dismissOverview(): RedirectResponse
    {
        $account = AccountSettings::getCurrent();
        $account->account_overviewed = true;
        $account->save();

        return back();
    }
}
