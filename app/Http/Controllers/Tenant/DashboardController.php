<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // Get the account from the request (set by EnsureTenantAccess middleware)
        $account = $request->get('tenant_account');
        
        // If not set, fetch it manually
        // Account model uses 'pgsql' connection (central/public schema) by default
        if (!$account) {
            $tenant = tenant();
            $account = \App\Models\Account::where('tenant_id', $tenant->id)
                ->with(['owner', 'users'])
                ->first();
        }

        return Inertia::render('Tenant/Dashboard', [
            'account' => $account ? [
                'id' => $account->id,
                'name' => $account->name,
                'owner' => $account->owner ? [
                    'id' => $account->owner->id,
                    'name' => $account->owner->name,
                    'email' => $account->owner->email,
                ] : null,
                'users_count' => $account->users()->count(),
            ] : null,
        ]);
    }
}
