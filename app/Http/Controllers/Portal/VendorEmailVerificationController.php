<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Notifications\VendorVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorEmailVerificationController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $contact = $request->user('vendor');
        if ($contact === null) {
            return redirect()->route('vendor.portal.login');
        }

        if ($contact->email_verified_at !== null) {
            return redirect()->route('vendor.portal.index');
        }

        return Inertia::render('VendorPortal/VerifyEmail', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $contact = $request->user('vendor');
        if ($contact === null) {
            return redirect()->route('vendor.portal.login');
        }

        if ($contact->email_verified_at !== null) {
            return redirect()->route('vendor.portal.index');
        }

        $contact->notify(new VendorVerifyEmail);

        return back()->with('status', 'A fresh verification link has been sent to your email address.');
    }
}
