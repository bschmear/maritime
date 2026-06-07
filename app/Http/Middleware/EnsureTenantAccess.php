<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\User;
use App\Services\WorkspacePlanCache;
use App\Support\Central\TenantAccountCache;
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

        // Account model uses 'pgsql' connection (central/public schema) by default
        $account = TenantAccountCache::findByTenantId($tenant->id);

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

        $this->preloadWorkspaceSubscriptions($account, $user);

        // Cache central plan features for this workspace (plans table is not on tenant DB)
        WorkspacePlanCache::ensureForAccount($account);

        return $next($request);
    }

    /**
     * Load Cashier subscriptions once per request (plan cache, billing middleware, Inertia trial props).
     */
    private function preloadWorkspaceSubscriptions(Account $account, User $user): void
    {
        $user->loadMissing('subscriptions.items');
        $account->loadMissing('owner');

        $owner = $account->owner;
        if ($owner === null) {
            return;
        }

        if ($owner->is($user)) {
            $owner->setRelation('subscriptions', $user->subscriptions);

            return;
        }

        $owner->loadMissing('subscriptions.items');
    }
}
