<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Customer\Models\Customer;
use App\Http\Controllers\Controller;
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
        return Inertia::render('Portal/Register', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if (!$customer) {
            return back()->withErrors([
                'email' => 'No customer account was found with that email address. Please contact us for access.',
            ]);
        }

        if ($customer->hasPortalAccount()) {
            return back()->withErrors([
                'email' => 'An account already exists for this email. Please log in instead.',
            ]);
        }

        $customer->update([
            'password' => Hash::make($request->password),
        ]);

        Auth::guard('customer')->login($customer);

        $request->session()->regenerate();

        return redirect()->route('portal.index');
    }
}
