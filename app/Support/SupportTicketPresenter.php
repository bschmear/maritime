<?php

namespace App\Support;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\TicketResponse;

class SupportTicketPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(SupportTicket $ticket): array
    {
        $status = $ticket->resolveStatus();

        return [
            'id' => $ticket->id,
            'uid' => $ticket->uid,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $status->value,
            'status_label' => $ticket->status_label,
            'status_color' => $ticket->status_color,
            'category_label' => $ticket->category_label,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'date_submitted' => $ticket->date_submitted?->toIso8601String(),
            'user' => $ticket->relationLoaded('user') && $ticket->user
                ? $ticket->user->only(['id', 'name', 'email'])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forDetail(SupportTicket $ticket): array
    {
        $status = $ticket->resolveStatus();

        $ticket->loadMissing(['user:id,name,email', 'publicResponses.user:id,name,email']);

        return [
            'id' => $ticket->id,
            'uid' => $ticket->uid,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'status' => $status->value,
            'status_label' => $ticket->status_label,
            'status_color' => $ticket->status_color,
            'category_label' => $ticket->category_label,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'date_submitted' => $ticket->date_submitted?->toIso8601String(),
            'is_replyable' => $status->isActive(),
            'is_solved' => $status === SupportTicketStatus::Resolved || $ticket->solved,
            'is_closed' => $status === SupportTicketStatus::Closed,
            'user' => $ticket->user ? [
                'id' => $ticket->user->id,
                'name' => $ticket->user->name,
                'email' => $ticket->user->email,
            ] : null,
            'public_responses' => $ticket->publicResponses->map(
                fn (TicketResponse $response) => self::forResponse($response)
            )->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forKioskDetail(SupportTicket $ticket): array
    {
        $data = self::forDetail($ticket);

        $ticket->loadMissing(['responses.user:id,name,email', 'tenant']);

        $data['responses'] = $ticket->responses
            ->map(fn (TicketResponse $response) => self::forResponse($response))
            ->values()
            ->all();

        $data['tenant'] = $ticket->tenant ? [
            'id' => $ticket->tenant->id,
        ] : null;

        $data['agent'] = $ticket->agent;
        $data['escalated'] = $ticket->escalated;
        $data['priority'] = $ticket->priority instanceof \App\Enums\SupportTicketPriority
            ? $ticket->priority->value
            : (int) $ticket->priority;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forResponse(TicketResponse $response): array
    {
        $response->loadMissing('user:id,name,email');

        return [
            'id' => $response->id,
            'response' => $response->response,
            'internal' => $response->internal,
            'created_at' => $response->created_at?->toIso8601String(),
            'user' => $response->user ? [
                'id' => $response->user->id,
                'name' => $response->user->name,
                'email' => $response->user->email,
            ] : null,
        ];
    }
}
