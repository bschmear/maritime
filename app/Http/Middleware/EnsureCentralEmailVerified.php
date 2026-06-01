<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the central (marketing) app, authenticated users must verify email before any other page.
 */
class EnsureCentralEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        $user = $request->user('web');

        if ($user === null || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        if ($request->routeIs(
            'verification.notice',
            'verification.verify',
            'verification.send',
            'logout',
        )) {
            return $next($request);
        }

        return redirect()->route('verification.notice');
    }

    protected function shouldBypass(Request $request): bool
    {
        if ($this->isTenantSubdomain($request) || $this->isKioskSubdomain($request)) {
            return true;
        }

        $helpHost = config('app.help_portal_host');
        if ($helpHost && $request->getHost() === $helpHost) {
            return true;
        }

        return false;
    }

    protected function isTenantSubdomain(Request $request): bool
    {
        $parts = explode('.', $request->getHost());

        return count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0]) === 1;
    }

    protected function isKioskSubdomain(Request $request): bool
    {
        $parts = explode('.', $request->getHost());

        return count($parts) >= 2 && ($parts[0] ?? '') === 'kiosk';
    }
}
