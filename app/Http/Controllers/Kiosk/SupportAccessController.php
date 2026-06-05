<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Support\ProvisionSupportTenantUser;
use App\Support\SupportWorkspaceSession;
use App\Support\TenantDashboardUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SupportAccessController extends Controller
{
    public function store(
        Request $request,
        Account $account,
        ProvisionSupportTenantUser $provisionSupportTenantUser,
    ): RedirectResponse|Response {
        $user = $request->user();

        abort_unless($user?->is_support, 403);
        abort_unless($account->allow_support_access, 403);
        abort_unless($account->tenant_id, 404);

        $account->loadMissing('tenant.domains');
        abort_unless($account->tenant?->domains?->first()?->domain, 404);

        if ($account->tenant) {
            $provisionSupportTenantUser->ensure($user, $account->tenant);
        }

        SupportWorkspaceSession::grant($account, $user);

        $user->forceFill(['current_tenant_id' => $account->tenant_id])->save();

        return Inertia::location(TenantDashboardUrl::forAccount($account));
    }
}
