<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorContactEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $contact = $request->user('vendor');

        if ($contact === null) {
            return $next($request);
        }

        if ($contact->email_verified_at !== null) {
            return $next($request);
        }

        if ($request->routeIs(
            'vendor.portal.verification.notice',
            'vendor.portal.verification.send',
            'vendor.portal.logout',
        )) {
            return $next($request);
        }

        return redirect()
            ->route('vendor.portal.verification.notice');
    }
}
