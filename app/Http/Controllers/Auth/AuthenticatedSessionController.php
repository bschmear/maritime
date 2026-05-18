<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        $invitation = null;
        $invitationToken = $request->query('invitation');

        if ($invitationToken) {
            $invitation = Invitation::with(['account', 'inviter'])
                ->where('token', $invitationToken)
                ->first();
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'invitation' => $invitation,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * Logins use Inertia `useForm` (X-Inertia + XSRF cookie). After session regeneration, return
     * `Inertia::location` so the client performs a full document load (fresh shell + CSRF meta).
     * Failed auth stays a 422 from {@see LoginRequest} — never a 419 from a stale meta `_token`.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($request->filled('invitation_token')) {
            $url = route('invitations.show', [
                'token' => $request->input('invitation_token'),
            ], absolute: true);
        } else {
            $url = redirect()->intended(route('dashboard', absolute: true))->getTargetUrl();
        }

        return Inertia::location($url);
    }

    /**
     * Destroy an authenticated session.
     *
     * On tenant workspace hosts (6-digit subdomains), avoid redirecting to `/` first: the next
     * Inertia request would be redirected to the central {@see config('app.url')} login URL, and
     * following that cross-origin redirect via XHR fails the browser CORS check. Use
     * {@see Inertia::location()} so the client performs a full document navigation to central login.
     */
    public function destroy(Request $request): RedirectResponse|SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($this->isTenantWorkspaceHost($request)) {
            $base = rtrim((string) config('app.url'), '/');
            $login = $request->isPwa() ? $base.'/login?pwa=1' : $base.'/login';

            return Inertia::location($login);
        }

        if ($request->isPwa()) {
            return redirect()->route('login', ['pwa' => 1]);
        }

        return redirect('/');
    }

    private function isTenantWorkspaceHost(Request $request): bool
    {
        $parts = explode('.', $request->getHost());

        return count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0]) === 1;
    }
}
