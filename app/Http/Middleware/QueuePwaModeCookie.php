<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QueuePwaModeCookie
{
    /**
     * When the PWA is opened with ?pwa=1, remember it so the server can use PWA context on later navigations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->boolean('pwa')) {
            $minutes = 60 * 24 * 365; // 1 year
            $secure = (bool) config('session.secure', false) ?: $request->isSecure();

            cookie()->queue(
                cookie('pwa_mode', '1', $minutes, '/', null, $secure, true, false, 'Lax')
            );
        }

        return $next($request);
    }
}
