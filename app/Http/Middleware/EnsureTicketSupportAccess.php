<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Services\WorkspacePlanCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTicketSupportAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();
        if (! $tenant) {
            abort(403);
        }

        $hasAccess = WorkspacePlanCache::get() !== null
            ? WorkspacePlanCache::hasTicketSupportAccess()
            : (Account::query()->where('tenant_id', $tenant->id)->first()?->hasTicketSupportAccess() ?? false);

        if (! $hasAccess) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your plan does not include support tickets. Please upgrade.',
                ], 403);
            }

            return redirect()
                ->route('dashHelp')
                ->with('error', 'Your plan does not include support tickets. Please upgrade.');
        }

        return $next($request);
    }
}
