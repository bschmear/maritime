<?php

namespace App\Http\Controllers\Kiosk;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
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

    public function index(Request $request): Response
    {
        $query = SupportTicket::query()->with(['user:id,name,email', 'tenant']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tickets = $query->latest('date_submitted')->paginate(20)->through(
            fn (SupportTicket $ticket) => SupportTicketPresenter::forList($ticket)
        );

        return Inertia::render('Kiosk/Support/Index', [
            'tickets' => $tickets,
            'statusOptions' => self::statusOptions(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(SupportTicket $support_ticket): Response
    {
        $support_ticket->load([
            'user:id,name,email',
            'tenant',
            'responses.user:id,name,email',
        ]);

        return Inertia::render('Kiosk/Support/Show', [
            'ticket' => SupportTicketPresenter::forKioskDetail($support_ticket),
            'statusOptions' => self::statusOptions(),
        ]);
    }

    public function update(Request $request, SupportTicket $support_ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|integer|min:1|max:5',
            'agent' => 'nullable|string|max:255',
            'escalated' => 'boolean',
            'priority' => 'nullable|integer|min:1|max:4',
        ]);

        if (isset($validated['status']) && (int) $validated['status'] === SupportTicketStatus::Closed->value) {
            $validated['completed'] = true;
            $validated['time_completed'] = now();
        }

        $support_ticket->update($validated);

        return redirect()
            ->route('kiosk.support-tickets.show', $support_ticket)
            ->with('success', 'Ticket updated.');
    }

    public function storeResponse(Request $request, SupportTicket $support_ticket): RedirectResponse
    {
        $validated = $request->validate([
            'response' => 'required|string',
            'internal' => 'boolean',
        ]);

        $this->tickets->addResponse(
            $support_ticket,
            auth()->user(),
            $validated['response'],
            $validated['internal'] ?? false,
        );

        return redirect()
            ->route('kiosk.support-tickets.show', $support_ticket)
            ->with('success', 'Response added.');
    }

    /**
     * @return array<int, array{value: int, label: string, color: string}>
     */
    private static function statusOptions(): array
    {
        return collect(SupportTicketStatus::cases())
            ->map(fn (SupportTicketStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ])
            ->values()
            ->all();
    }
}
