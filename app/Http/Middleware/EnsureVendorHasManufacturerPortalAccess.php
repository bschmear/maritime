<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect vendor-portal users who are not enabled on any manufacturer (contact_vendor.portal_access).
 */
class EnsureVendorHasManufacturerPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('vendor.portal.no-access')) {
            return $next($request);
        }

        $contact = $request->user('vendor');
        if ($contact === null) {
            return $next($request);
        }

        if ($contact->vendorsWithPortalAccess()->exists()) {
            return $next($request);
        }

        return redirect()->route('vendor.portal.no-access');
    }
}
