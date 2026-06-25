<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerContactEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $contact = $request->user('customer');

        if ($contact === null) {
            return $next($request);
        }

        if ($contact->email_verified_at !== null) {
            return $next($request);
        }

        if ($request->routeIs(
            'portal.verification.notice',
            'portal.verification.send',
            'portal.logout',
        )) {
            return $next($request);
        }

        return redirect()
            ->route('portal.verification.notice');
    }
}
