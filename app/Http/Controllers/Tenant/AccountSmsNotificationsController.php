<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\SMS;
use App\Models\AccountSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountSmsNotificationsController extends Controller
{
    public function index(): Response
    {
        $account = AccountSettings::getCurrent();
        $prefs = $account->getOrCreateSmsNotificationPreference();

        return Inertia::render('Tenant/Account/SmsNotifications', [
            'account' => $account->only(['id', 'sms_enabled', 'sandbox_mode']),
            'smsPreferences' => $prefs->toPreferenceMap(),
            'smsNotificationTypes' => collect(SMS::cases())->map(fn (SMS $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ])->values()->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'sms_enabled' => 'required|boolean',
            'preferences' => 'required|array',
        ];

        foreach (SMS::cases() as $case) {
            $rules['preferences.'.$case->value] = 'required|boolean';
        }

        $validated = $request->validate($rules);

        $account = AccountSettings::getCurrent();
        $account->sms_enabled = $validated['sms_enabled'];
        $account->save();

        $pref = $account->getOrCreateSmsNotificationPreference();
        foreach (SMS::cases() as $case) {
            $pref->setAttribute(
                $case->notifyColumn(),
                $validated['preferences'][$case->value],
            );
        }
        $pref->save();

        return back()->with('success', 'Text notification settings saved.');
    }
}
