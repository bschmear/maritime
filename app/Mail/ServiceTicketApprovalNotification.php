<?php

namespace App\Mail;

use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\User\Models\User;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ServiceTicketApprovalNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ServiceTicket $ticket;
    public AccountSettings $account;
    public User $user;
    public ?string $pdfPath;

    public function __construct(ServiceTicket $ticket, AccountSettings $account, User $user, ?string $pdfPath)
    {
        $this->ticket = $ticket;
        $this->account = $account;
        $this->user = $user;
        $this->pdfPath = $pdfPath;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Service Ticket Approved - {$this->ticket->service_ticket_number}",
        );
    }

    public function content(): Content
    {
        $domain = tenant()->domains->first()?->domain;
        $ticketUrl = $domain
            ? "https://{$domain}/servicetickets/{$this->ticket->id}"
            : '#';

        return new Content(
            view: 'emails.service-ticket-approval-notification',
            with: [
                'ticket' => $this->ticket,
                'account' => $this->account,
                'user' => $this->user,
                'ticketUrl' => $ticketUrl,
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->pdfPath && Storage::disk('s3')->exists($this->pdfPath)) {
            $attachments[] = Attachment::fromStorageDisk('s3', $this->pdfPath)
                ->as("service-ticket-{$this->ticket->service_ticket_number}.pdf")
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
