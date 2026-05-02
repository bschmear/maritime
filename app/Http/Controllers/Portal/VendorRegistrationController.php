<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Contact\Models\Contact;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Notifications\VendorVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class VendorRegistrationController extends Controller
{
    public function create(): Response
    {
        $account = AccountSettings::getCurrent();
        $settings = is_array($account->settings) ? $account->settings : [];
        $companyName = $settings['business_name'] ?? 'Vendor Portal';

        return Inertia::render('VendorPortal/Register', [
            'status' => session('status'),
            'logoUrl' => $account->logo_url ?? null,
            'companyName' => $companyName,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $contact = Contact::findByEmailCaseInsensitive($request->input('email'));

        if (! $contact) {
            return back()->withErrors([
                'email' => 'No manufacturer contact was found with that email address. Please contact us for access.',
            ]);
        }

        if (! $contact->vendors()->exists()) {
            return back()->withErrors([
                'email' => 'No manufacturer contact was found with that email address. Please contact us for access.',
            ]);
        }

        if ($contact->hasPortalAccount()) {
            return back()->withErrors([
                'email' => 'An account already exists for this email. Sign in to the vendor portal with your password.',
            ]);
        }

        $contact->password = Hash::make($request->password);
        $contact->email_verified_at = null;
        $contact->save();

        $contact->notify(new VendorVerifyEmail);

        return redirect()
            ->route('vendor.portal.login')
            ->with('status', 'We sent a verification link to your email. Please verify your address, then sign in.');
    }
}
