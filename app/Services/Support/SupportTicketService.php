<?php

namespace App\Services\Support;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\TicketResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupportTicketService
{
    /**
     * @param  array{subject: string, message: string, category?: int, priority?: int}  $data
     */
    public function createForTenant(string $tenantId, User $user, array $data): SupportTicket
    {
        return DB::connection('pgsql')->transaction(function () use ($tenantId, $user, $data) {
            return SupportTicket::create([
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'subject' => $data['subject'],
                'message' => $data['message'],
                'category' => $data['category'] ?? 1,
                'priority' => $data['priority'] ?? 2,
                'status' => SupportTicketStatus::Open,
                'date_submitted' => now(),
            ]);
        });
    }

    public function addResponse(
        SupportTicket $ticket,
        User $user,
        string $body,
        bool $internal = false,
    ): TicketResponse {
        return DB::connection('pgsql')->transaction(function () use ($ticket, $user, $body, $internal) {
            $response = $ticket->responses()->create([
                'user_id' => $user->id,
                'response' => $body,
                'internal' => $internal,
            ]);

            if ($ticket->status === SupportTicketStatus::WaitingOnCustomer && ! $internal) {
                $ticket->update(['status' => SupportTicketStatus::InProgress]);
            }

            return $response;
        });
    }

    public function reopen(SupportTicket $ticket): SupportTicket
    {
        $ticket->update([
            'reopened' => true,
            'completed' => false,
            'solved' => false,
            'time_completed' => null,
            'status' => SupportTicketStatus::Open,
        ]);

        return $ticket->fresh();
    }
}
