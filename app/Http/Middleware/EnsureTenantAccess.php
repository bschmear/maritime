<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Services\WorkspacePlanCache;
use App\Support\SupportWorkspaceSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        // Get the current tenant
        $tenant = tenant();

        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        // Find the account linked to this tenant
        // Account model uses 'pgsql' connection (central/public schema) by default
        $account = Account::where('tenant_id', $tenant->id)->first();

        if (! $account) {
            abort(404, 'Account not found for this tenant.');
        }

        // Check if the authenticated user belongs to this account
        // Users table is also in central database
        $user = auth()->user();
        $hasAccess = $account->users()->where('users.id', $user->id)->exists()
                  || $account->owner_id === $user->id;

        if (! $hasAccess && SupportWorkspaceSession::allows($request, $account, $user)) {
            $hasAccess = true;
        }

        if (! $hasAccess) {
            abort(403, 'You do not have access to this tenant.');
        }

        // Share the account with the request for easy access in controllers
        $request->merge(['tenant_account' => $account]);

        // Cache central plan features for this workspace (plans table is not on tenant DB)
        WorkspacePlanCache::ensureForAccount($account);

        return $next($request);
    }
}
