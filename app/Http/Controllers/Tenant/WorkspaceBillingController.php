<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceBillingController extends Controller
{
    /**
     * Full-page notice when the workspace has no active subscription.
     *
     * Registered outside {@see \App\Http\Middleware\EnsureActiveWorkspaceSubscription}
     * so users can load this screen without a redirect loop.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        /** @var Account|null $account */
        $account = $request->get('tenant_account');
        if (! $account instanceof Account) {
            abort(404);
        }

        $account->loadMissing('owner');

        if ($account->hasActiveSubscription()) {
            return redirect()->route('dashboard');
        }

        $centralBase = rtrim((string) config('app.url'), '/');

        return Inertia::render('Tenant/SubscriptionRequired', [
            'workspace_name' => $account->name,
            'central_dashboard_url' => $centralBase.'/dashboard',
            'account_management_url' => $centralBase.'/accounts/'.$account->id,
            'reactivate_pricing_url' => $centralBase.'/pricing?existing_account_id='.$account->id,
        ]);
    }
}
