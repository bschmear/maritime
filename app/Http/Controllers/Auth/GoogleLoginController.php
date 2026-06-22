<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Google\GoogleLoginService;
use App\Services\Google\GoogleOAuthService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $handoffId = (string) Str::uuid();
        $central = (string) config('tenancy.database.central_connection', config('database.default'));

        DB::connection($central)->table('google_login_handoffs')->insert([
            'id' => $handoffId,
            'return_origin' => $this->resolveReturnOrigin($request),
            'invitation_token' => $request->filled('invitation') ? (string) $request->input('invitation') : null,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $redirectUri = $this->loginRedirectUri();

        return redirect()->away($oauth->loginAuthorizeUrl($handoffId, $redirectUri));
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

        $handoffId = (string) $request->input('state', '');
        $code = (string) $request->input('code', '');

        if ($handoffId === '' || $code === '') {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google did not return the required authorization data.']);
        }

        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        $handoff = DB::connection($central)->table('google_login_handoffs')->where('id', $handoffId)->first();

        if (! $handoff || now()->gt(Carbon::parse($handoff->expires_at))) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in expired. Please try again.']);
        }

        try {
            $tokens = $oauth->exchangeCode($code, $this->loginRedirectUri());
            $profile = $oauth->fetchUserProfile($tokens['access_token']);
            $user = $login->resolveUser($profile);
        } catch (\Throwable $e) {
            Log::warning('Google login callback failed', ['error' => $e->getMessage()]);

            DB::connection($central)->table('google_login_handoffs')->where('id', $handoffId)->delete();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Unable to sign in with Google. Please try again or use your email and password.']);
        }

        DB::connection($central)->table('google_login_handoffs')->where('id', $handoffId)->update([
            'user_id' => $user->id,
            'updated_at' => now(),
        ]);

        $completeUrl = rtrim((string) $handoff->return_origin, '/').'/auth/google/complete/'.$handoffId;

        return redirect()->away($completeUrl);
    }

    public function complete(Request $request, string $handoff): RedirectResponse|SymfonyResponse
    {
        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        $row = DB::connection($central)->table('google_login_handoffs')->where('id', $handoff)->first();

        if (! $row || now()->gt(Carbon::parse($row->expires_at)) || $row->user_id === null) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in expired. Please try again.']);
        }

        if (rtrim($request->getSchemeAndHttpHost(), '/') !== rtrim((string) $row->return_origin, '/')) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in could not be completed for this site.']);
        }

        $user = User::query()->find($row->user_id);
        if ($user === null) {
            DB::connection($central)->table('google_login_handoffs')->where('id', $handoff)->delete();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your account could not be found. Please try again.']);
        }

        DB::connection($central)->table('google_login_handoffs')->where('id', $handoff)->delete();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if (filled($row->invitation_token)) {
            $url = route('invitations.show', ['token' => $row->invitation_token], absolute: true);
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

    private function resolveReturnOrigin(Request $request): string
    {
        $origin = $request->getSchemeAndHttpHost();

        if ($this->isAllowedReturnOrigin($origin)) {
            return $origin;
        }

        return rtrim((string) config('app.url'), '/');
    }

    private function isAllowedReturnOrigin(string $origin): bool
    {
        if ($this->isAllowedLoginOrigin($origin)) {
            return true;
        }

        $loginHost = parse_url($this->loginRedirectUri(), PHP_URL_HOST);
        $originHost = parse_url($origin, PHP_URL_HOST);

        return filled($loginHost) && $originHost === $loginHost;
    }

    private function isAllowedLoginOrigin(string $origin): bool
    {
        $host = parse_url($origin, PHP_URL_HOST) ?: $origin;

        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return true;
        }

        $allowedHosts = array_filter(array_map(
            fn (string $domain) => parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST) ?: $domain,
            array_merge(
                (array) config('tenancy.central_domains', []),
                [parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''],
            ),
        ));

        return in_array($host, $allowedHosts, true);
    }
}
