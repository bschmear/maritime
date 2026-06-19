<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInboundEmailRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('inbound_email.verify_signature', true)) {
            return $next($request);
        }

        $secret = config('inbound_email.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            return $next($request);
        }

        $provided = $request->header('X-Inbound-Email-Secret');
        if (! is_string($provided) || ! hash_equals($secret, $provided)) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
