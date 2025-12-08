<?php

namespace App\Mail;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public Invitation $invitation;
    public Account $account;
    public User $inviter;

    /**
     * Create a new message instance.
     */
    public function __construct(Invitation $invitation, Account $account, User $inviter)
    {
        $this->invitation = $invitation;
        $this->account = $account;
        $this->inviter = $inviter;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->account->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
            with: [
                'invitation' => $this->invitation,
                'account' => $this->account,
                'inviter' => $this->inviter,
                'invitationUrl' => $this->invitation->getInvitationUrl(),
                'role' => ucfirst($this->invitation->role),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}