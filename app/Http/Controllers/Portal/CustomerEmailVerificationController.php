<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Notifications\CustomerVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerEmailVerificationController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $contact = $request->user('customer');
        if ($contact === null) {
            return redirect()->route('portal.login');
        }

        if ($contact->email_verified_at !== null) {
            return redirect()->route('portal.index');
        }

        $status = session('status');
        if (! $request->session()->get('customer_verification_email_sent')) {
            $contact->notify(new CustomerVerifyEmail);
            $request->session()->put('customer_verification_email_sent', true);
            $status = $status ?: 'We sent a verification link to your email address.';
        }

        $account = AccountSettings::getCurrent();
        $settings = is_array($account->settings) ? $account->settings : [];

        return Inertia::render('Portal/VerifyEmail', [
            'status' => $status,
            'logoUrl' => $account->logo_url ?? null,
            'companyName' => $settings['business_name'] ?? 'Customer Portal',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $contact = $request->user('customer');
        if ($contact === null) {
            return redirect()->route('portal.login');
        }

        if ($contact->email_verified_at !== null) {
            return redirect()->route('portal.index');
        }

        $contact->notify(new CustomerVerifyEmail);
        $request->session()->put('customer_verification_email_sent', true);

        return back()->with('status', 'A fresh verification link has been sent to your email address.');
    }
}
