<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\User;
use App\Support\SupportWorkspaceSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks tenant CRM routes when the workspace has no active Stripe/Cashier subscription.
 *
 * Must run after {@see EnsureTenantAccess} so `tenant_account` is on the request.
 */
class EnsureActiveWorkspaceSubscription
{
    /**
     * Routes that must stay reachable without an active subscription (logout, profile).
     *
     * @var list<string>
     */
    private const EXEMPT_ROUTE_NAMES = [
        'logout',
        'profile.edit',
        'profile.update',
        'profile.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        if ($route && in_array($route->getName(), self::EXEMPT_ROUTE_NAMES, true)) {
            return $next($request);
        }

        /** @var Account|null $account */
        $account = $request->get('tenant_account');
        if (! $account instanceof Account) {
            return $next($request);
        }

        $account->loadMissing('owner');

        $user = $request->user('web');
        if ($user instanceof User && SupportWorkspaceSession::allows($request, $account, $user)) {
            return $next($request);
        }

        if ($account->hasActiveSubscription()) {
            return $next($request);
        }

        return redirect()->route('tenant.workspace.billing-required');
    }
}
