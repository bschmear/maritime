<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\InboundEmail\RouteAction;
use App\Models\AccountSettings;
use App\Models\EmailRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AiInboxController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): Response
    {
        $routes = EmailRoute::query()
            ->where('tenant_id', tenant('id'))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EmailRoute $route) => [
                'id' => $route->id,
                'address' => $route->address,
                'action' => $route->action->value,
                'action_label' => $route->action->label(),
                'is_active' => (bool) $route->is_active,
                'created_at' => $route->created_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Tenant/Account/AiInbox/Index', [
            'routes' => $routes,
            'actionOptions' => RouteAction::options(),
            'inboundDomain' => config('inbound_email.domain'),
            'account' => AccountSettings::getCurrent()->only(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'in:'.implode(',', array_column(RouteAction::cases(), 'value'))],
        ]);

        $action = $validated['action'] ?? RouteAction::CreateLead->value;

        $address = $this->generateUniqueAddress();

        EmailRoute::query()->create([
            'tenant_id' => tenant('id'),
            'address' => $address,
            'action' => $action,
            'is_active' => true,
        ]);

        return redirect()
            ->route('account.ai-inbox.index')
            ->with('success', 'Inbound email address created.');
    }

    public function update(EmailRoute $emailRoute): RedirectResponse
    {
        abort_unless($emailRoute->tenant_id === tenant('id'), 404);

        $emailRoute->update([
            'is_active' => ! $emailRoute->is_active,
        ]);

        $message = $emailRoute->is_active
            ? 'Inbound email address enabled.'
            : 'Inbound email address disabled.';

        return redirect()
            ->route('account.ai-inbox.index')
            ->with('success', $message);
    }

    public function destroy(EmailRoute $emailRoute): RedirectResponse
    {
        abort_unless($emailRoute->tenant_id === tenant('id'), 404);

        $emailRoute->delete();

        return redirect()
            ->route('account.ai-inbox.index')
            ->with('success', 'Inbound email address deleted.');
    }

    protected function generateUniqueAddress(): string
    {
        $domain = config('inbound_email.domain');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $token = (string) random_int(100000, 999999);
            $address = "lead-{$token}@{$domain}";

            if (! EmailRoute::query()->where('address', $address)->exists()) {
                return $address;
            }
        }

        $token = (string) random_int(100000000, 999999999);

        return "lead-{$token}@{$domain}";
    }
}
