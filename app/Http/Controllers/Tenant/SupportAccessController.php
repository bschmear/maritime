<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\KioskUrl;
use App\Support\SupportWorkspaceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SupportAccessController extends Controller
{
    public function exit(Request $request): RedirectResponse|Response
    {
        $session = SupportWorkspaceSession::current($request);
        $accountId = $session['account_id'] ?? null;

        SupportWorkspaceSession::clear();

        $url = $accountId
            ? KioskUrl::accountShow($accountId)
            : KioskUrl::dashboard();

        if ($request->inertia()) {
            return Inertia::location($url);
        }

        return redirect()->away($url);
    }
}
