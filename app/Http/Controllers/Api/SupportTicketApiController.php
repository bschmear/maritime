<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketApiController extends Controller
{
    public function __construct(
        private SupportTicketService $tickets,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->where('tenant_id', tenant()->id)
            ->where('user_id', auth()->id())
            ->with(['user:id,name,email'])
            ->latest('date_submitted')
            ->paginate(15);

        return response()->json($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'category' => 'nullable|integer|min:1|max:4',
            'priority' => 'nullable|integer|min:1|max:4',
        ]);

        $ticket = $this->tickets->createForTenant(
            tenant()->id,
            auth()->user(),
            $validated,
        );

        return response()->json($ticket->load('user:id,name,email'), 201);
    }

    public function show(SupportTicket $support_ticket): JsonResponse
    {
        $this->authorizeTicket($support_ticket);

        $support_ticket->load([
            'user:id,name,email',
            'publicResponses.user:id,name,email',
        ]);

        return response()->json($support_ticket);
    }

    public function storeResponse(Request $request, SupportTicket $support_ticket): JsonResponse
    {
        $this->authorizeTicket($support_ticket);

        $validated = $request->validate([
            'response' => 'required|string',
        ]);

        $response = $this->tickets->addResponse(
            $support_ticket,
            auth()->user(),
            $validated['response'],
        );

        return response()->json($response->load('user:id,name,email'), 201);
    }

    public function reopen(SupportTicket $support_ticket): JsonResponse
    {
        $this->authorizeTicket($support_ticket);

        return response()->json($this->tickets->reopen($support_ticket));
    }

    private function authorizeTicket(SupportTicket $ticket): void
    {
        abort_unless(
            $ticket->tenant_id === tenant()->id && $ticket->user_id === auth()->id(),
            403,
        );
    }
}
