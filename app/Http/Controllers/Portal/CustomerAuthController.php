<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CustomerLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Portal/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(CustomerLoginRequest $request): RedirectResponse
    {
        $request->attemptLogin(fn () => Auth::guard('customer')->attempt(
            $request->only('email', 'password'),
            $request->boolean('remember')
        ));

        $request->session()->regenerate();

        return redirect()->intended(route('portal.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
