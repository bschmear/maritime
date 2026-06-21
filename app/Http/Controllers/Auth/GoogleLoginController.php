<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Google\GoogleLoginService;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class GoogleLoginController extends Controller
{
    public function redirect(Request $request, GoogleOAuthService $oauth): RedirectResponse|SymfonyResponse
    {
        if (! self::isConfigured()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in is not configured.']);
        }

        $state = Str::random(40);
        $request->session()->put('google_login_state', $state);

        if ($request->filled('invitation')) {
            $request->session()->put('google_login_invitation', (string) $request->input('invitation'));
        }

        $redirectUri = $this->loginRedirectUri();

        return redirect()->away($oauth->loginAuthorizeUrl($state, $redirectUri));
    }

    public function callback(Request $request, GoogleOAuthService $oauth, GoogleLoginService $login): RedirectResponse|SymfonyResponse
    {
        if ($request->filled('error')) {
            $message = $request->input('error_description')
                ?: $request->input('error', 'Google sign-in was cancelled or denied.');

            return redirect()
                ->route('login')
                ->withErrors(['email' => $message]);
        }

        $expectedState = (string) $request->session()->pull('google_login_state', '');
        $state = (string) $request->input('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in expired. Please try again.']);
        }

        $code = (string) $request->input('code', '');
        if ($code === '') {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google did not return an authorization code.']);
        }

        try {
            $tokens = $oauth->exchangeCode($code, $this->loginRedirectUri());
            $profile = $oauth->fetchUserProfile($tokens['access_token']);
            $user = $login->resolveUser($profile);
        } catch (\Throwable $e) {
            Log::warning('Google login callback failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Unable to sign in with Google. Please try again or use your email and password.']);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $invitationToken = $request->session()->pull('google_login_invitation');

        if (filled($invitationToken)) {
            $url = route('invitations.show', ['token' => $invitationToken], absolute: true);
        } elseif ($user->hasVerifiedEmail() === false) {
            $url = route('verification.notice', absolute: true);
        } else {
            $url = redirect()->intended(route('dashboard', absolute: true))->getTargetUrl();
        }

        return Inertia::location($url);
    }

    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.login_redirect_uri'));
    }

    private function loginRedirectUri(): string
    {
        return (string) config('services.google.login_redirect_uri');
    }
}
