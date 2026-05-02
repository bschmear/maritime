<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearPwaModeCookie
{
    /**
     * Drop pwa_mode when the user opens any URL with ?pwa=0 (JS cannot clear HttpOnly cookies).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query('pwa') === '0') {
            $secure = (bool) config('session.secure', false) ?: $request->isSecure();
            // Legacy cookie was HttpOnly; client-set replacement is not — expire both shapes.
            cookie()->queue(cookie('pwa_mode', '', -2628000, '/', null, $secure, true, false, 'Lax'));
            cookie()->queue(cookie('pwa_mode', '', -2628000, '/', null, $secure, false, false, 'Lax'));
        }

        return $next($request);
    }
}
