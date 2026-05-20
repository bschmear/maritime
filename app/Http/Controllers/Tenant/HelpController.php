<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    public function index(): Response
    {
        $account = Account::query()->where('tenant_id', tenant()->id)->first();

        return Inertia::render('Tenant/Help/Index', [
            'helpPortalUrl' => config('app.help_portal'),
            'hasTicketSupport' => $account?->hasTicketSupportAccess() ?? false,
            'upgradeUrl' => config('app.url').'/pricing',
        ]);
    }

    public function documentation()
    {
        return redirect()->away(config('app.help_portal'));
    }
}
