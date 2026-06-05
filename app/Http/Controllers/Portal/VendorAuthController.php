<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\VendorLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VendorAuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('VendorPortal/Login', [
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function store(VendorLoginRequest $request): RedirectResponse
    {
        $request->attemptLogin(fn () => Auth::guard('vendor')->attempt(
            $request->only('email', 'password'),
            $request->boolean('remember')
        ));

        $contact = Auth::guard('vendor')->user();
        if ($contact && ! $contact->vendors()->exists()) {
            Auth::guard('vendor')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account is not linked to a manufacturer.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('vendor.portal.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('vendor')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('vendor.portal.login');
    }
}
