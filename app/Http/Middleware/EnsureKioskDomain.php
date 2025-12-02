<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKioskDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $kioskSubdomain = 'kiosk';
        
        // Check if the request is coming from kiosk subdomain
        // Allow localhost and .test domains for local development
        if (!str_starts_with($host, $kioskSubdomain . '.') && $host !== 'kiosk.localhost') {
            abort(403, 'Access denied. This area is only accessible via kiosk subdomain.');
        }

        return $next($request);
    }
}
