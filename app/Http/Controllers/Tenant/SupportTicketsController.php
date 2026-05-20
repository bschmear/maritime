<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use App\Support\SupportTicketPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketsController extends Controller
{
    public function __construct(
        private SupportTicketService $tickets,
    ) {}

    public function index(): Response
    {
        $userId = auth()->id();
        $tenantId = tenant()->id;

        $base = SupportTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId);

        $activeTicket = (clone $base)
            ->whereIn('status', [
                SupportTicketStatus::Open,
                SupportTicketStatus::InProgress,
                SupportTicketStatus::WaitingOnCustomer,
            ])
            ->latest('date_submitted')
            ->first();

        $inactiveQuery = (clone $base)->latest('date_submitted');

        if ($activeTicket) {
            $inactiveQuery->where('id', '!=', $activeTicket->id);
        }

        $inactiveTickets = $inactiveQuery->paginate(15)->through(
            fn (SupportTicket $ticket) => SupportTicketPresenter::forList($ticket)
        );

        return Inertia::render('Tenant/Support/Index', [
            'activeTicket' => $activeTicket
                ? SupportTicketPresenter::forList($activeTicket)
                : null,
            'inactiveTickets' => $inactiveTickets,
            'ticketCategories' => collect(SupportTicketCategory::cases())
                ->map(fn (SupportTicketCategory $c) => [
                    'id' => $c->value,
                    'name' => $c->label(),
                ])
                ->values()
                ->all(),
            'canCreateTicket' => $activeTicket === null,
        ]);
    }

    public function show(Request $request): Response
    {
        $account = Account::query()->where('tenant_id', tenant()->id)->first();
        abort_unless($account?->hasTicketSupportAccess(), 403);

        $ticket = SupportTicket::query()
            ->where('tenant_id', tenant()->id)
            ->where('user_id', auth()->id())
            ->where('uid', $request->query('uid'))
            ->firstOrFail();

        return Inertia::render('Tenant/Support/Show', [
            'ticket' => SupportTicketPresenter::forDetail($ticket),
            'appName' => config('app.name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hasActive = SupportTicket::query()
            ->where('tenant_id', tenant()->id)
            ->where('user_id', auth()->id())
            ->whereIn('status', [
                SupportTicketStatus::Open,
                SupportTicketStatus::InProgress,
                SupportTicketStatus::WaitingOnCustomer,
            ])
            ->exists();

        if ($hasActive) {
            return redirect()
                ->route('dashSupport')
                ->with('error', 'You can only have one open ticket at a time.');
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'category' => 'required|integer|min:1|max:4',
            'priority' => 'nullable|integer|min:1|max:4',
        ]);

        $ticket = $this->tickets->createForTenant(
            tenant()->id,
            auth()->user(),
            $validated,
        );

        return redirect()
            ->route('showTicket', ['uid' => $ticket->uid])
            ->with('success', 'Support ticket submitted.');
    }

    public function reply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'uid' => 'required|string',
            'response' => 'required|string',
        ]);

        $ticket = SupportTicket::query()
            ->where('tenant_id', tenant()->id)
            ->where('user_id', auth()->id())
            ->where('uid', $validated['uid'])
            ->firstOrFail();

        abort_unless($ticket->status->isActive(), 403);

        $this->tickets->addResponse($ticket, auth()->user(), $validated['response']);

        return redirect()
            ->route('showTicket', ['uid' => $ticket->uid])
            ->with('success', 'Reply sent.');
    }

    public function reopen(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'uid' => 'required|string',
        ]);

        $ticket = SupportTicket::query()
            ->where('tenant_id', tenant()->id)
            ->where('user_id', auth()->id())
            ->where('uid', $validated['uid'])
            ->firstOrFail();

        if ($ticket->status === SupportTicketStatus::Closed) {
            return redirect()
                ->route('showTicket', ['uid' => $ticket->uid])
                ->with('error', 'Closed tickets cannot be reopened.');
        }

        $this->tickets->reopen($ticket);

        return redirect()
            ->route('showTicket', ['uid' => $ticket->uid])
            ->with('success', 'Ticket reopened.');
    }

    public function update(Request $request): RedirectResponse
    {
        return redirect()->back();
    }
}
