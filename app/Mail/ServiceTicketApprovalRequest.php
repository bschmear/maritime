<?php

namespace App\Mail;

use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServiceTicketApprovalRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ServiceTicket $serviceTicket;
    public AccountSettings $account;
    public string $approvalUrl;

    public function __construct(array $emailData)
    {
        $this->serviceTicket = $emailData['service_ticket'];
        $this->account = $emailData['account'];
        $this->approvalUrl = $emailData['approval_url'];
    }

    public function envelope(): Envelope
    {
        $companyName = $this->serviceTicket->subsidiary->display_name ?? 'Service Provider';

        return new Envelope(
            subject: "Approval Requested: Service Ticket #{$this->serviceTicket->service_ticket_number} — {$companyName}",
        );
    }

    public function content(): Content
    {
        $ticket = $this->serviceTicket;

        $billableItems = $ticket->serviceItems->filter(fn ($item) => $item->billable !== false && !$item->inactive);
        $subtotal = $billableItems->sum(function ($item) {
            $rate = (float) $item->unit_price;
            $quantity = (float) ($item->quantity ?: 1);
            $hours = (float) ($item->estimated_hours ?: 0);

            return match ($item->billing_type) {
                1 => $hours * $rate,
                2 => $rate,
                default => $quantity * $rate,
            };
        });

        $taxRate = (float) ($ticket->tax_rate ?? 0);
        $tax = $subtotal * ($taxRate / 100);
        $total = $subtotal + $tax;

        return new Content(
            view: 'emails.service-ticket-approval-request',
            with: [
                'serviceTicket' => $ticket,
                'account' => $this->account,
                'approvalUrl' => $this->approvalUrl,
                'subtotal' => $subtotal,
                'taxRate' => $taxRate,
                'tax' => $tax,
                'total' => $total,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
