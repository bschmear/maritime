<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Get the current tenant
        $tenant = tenant();
        
        if (!$tenant) {
            abort(404, 'Tenant not found.');
        }

        // Find the account linked to this tenant
        // Account model uses 'pgsql' connection (central/public schema) by default
        $account = \App\Models\Account::where('tenant_id', $tenant->id)->first();
        
        if (!$account) {
            abort(404, 'Account not found for this tenant.');
        }

        // Check if the authenticated user belongs to this account
        // Users table is also in central database
        $user = auth()->user();
        $hasAccess = $account->users()->where('users.id', $user->id)->exists() 
                  || $account->owner_id === $user->id;

        if (!$hasAccess) {
            abort(403, 'You do not have access to this tenant.');
        }

        // Share the account with the request for easy access in controllers
        $request->merge(['tenant_account' => $account]);

        return $next($request);
    }
}

