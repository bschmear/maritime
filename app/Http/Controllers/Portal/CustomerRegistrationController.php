<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Contact\Models\Contact;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class CustomerRegistrationController extends Controller
{
    public function create(): Response
    {
        $account = AccountSettings::getCurrent();
        $settings = is_array($account->settings) ? $account->settings : [];
        $companyName = $settings['business_name'] ?? 'Customer Portal';

        return Inertia::render('Portal/Register', [
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
                'email' => 'No customer account was found with that email address. Please contact us for access.',
            ]);
        }

        if (! $contact->customer) {
            return back()->withErrors([
                'email' => 'No customer account was found with that email address. Please contact us for access.',
            ]);
        }

        if ($contact->hasPortalAccount()) {
            return back()->withErrors([
                'email' => 'An account already exists for this email. Please log in instead.',
            ]);
        }

        $contact->password = Hash::make($request->password);
        $contact->save();

        Auth::guard('customer')->login($contact);

        $request->session()->regenerate();

        return redirect()->route('portal.index');
    }
}
