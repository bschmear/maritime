<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectUnauthenticatedFromTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is a tenant subdomain (6-digit number pattern)
        $host = $request->getHost();
        $parts = explode('.', $host);

        $isTenantSubdomain = count($parts) >= 2 && preg_match('/^\d{6}$/', $parts[0]);

        // If we're on a tenant subdomain and user is not authenticated
        if ($isTenantSubdomain && !auth()->check()) {
            // Get the main domain URL from config
            $mainDomainUrl = config('app.url');

            // Log for debugging
            \Log::info('Redirecting unauthenticated tenant subdomain request', [
                'host' => $host,
                'is_tenant_subdomain' => $isTenantSubdomain,
                'authenticated' => auth()->check(),
                'main_domain_url' => $mainDomainUrl,
                'current_url' => $request->fullUrl(),
                'path' => $request->getPathInfo()
            ]);

            // If we have a main domain URL, redirect there
            if ($mainDomainUrl) {
                return redirect($mainDomainUrl);
            }

            // Fallback: redirect to root of main domain
            return redirect(config('app.url', '/'));
        }

        return $next($request);
    }
}