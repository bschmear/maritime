<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Invitation;
use App\Support\Turnstile;
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
            'turnstileSiteKey' => Turnstile::siteKey(),
            'googleLoginEnabled' => GoogleLoginController::isConfigured(),
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
        } elseif ($request->user('web')?->hasVerifiedEmail() === false) {
            $url = route('verification.notice', absolute: true);
        } else {
            $url = redirect()->intended(route('dashboard', absolute: true))->getTargetUrl();
        }

        return Inertia::location($url);
    }

    /**
     * Destroy an authenticated session.
     *
     * Match login: return {@see Inertia::location()} for Inertia visits so the browser performs a
     * full document load (fresh CSRF meta + session cookie). Plain redirects leave a stale SPA
     * shell and cause 419s on the next POST.
     *
     * Tenant workspace hosts must land on central login (cross-origin); non-Inertia fallbacks
     * use normal redirects.
     */
    public function destroy(Request $request): RedirectResponse|SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        $url = $this->logoutRedirectUrl($request);

        if ($request->inertia()) {
            return Inertia::location($url);
        }

        return redirect($url);
    }

    private function logoutRedirectUrl(Request $request): string
    {
        if ($this->isTenantWorkspaceHost($request)) {
            $base = rtrim((string) config('app.url'), '/');

            return $request->isPwa() ? $base.'/login?pwa=1' : $base.'/login';
        }

        if ($request->isPwa()) {
            return route('login', ['pwa' => 1], absolute: true);
        }

        return url('/');
    }

    private function isTenantWorkspaceHost(Request $request): bool
    {
        $parts = explode('.', $request->getHost());

        return count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0]) === 1;
    }
}
