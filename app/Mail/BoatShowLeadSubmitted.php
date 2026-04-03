<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoatShowLeadSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{display_name: string}>  $interestedAssets
     */
    public function __construct(
        public string $eventName,
        public string $leadFullName,
        public ?string $leadEmail,
        public ?string $leadPhone,
        public ?string $leadNotes,
        public array $interestedAssets,
        public AccountSettings $account,
        public string $tenantLabel,
        public bool $isOwnerCopy = true,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isOwnerCopy
            ? "New boat show lead: {$this->leadFullName} — {$this->eventName}"
            : "We received your interest — {$this->eventName}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.boat-show-lead-submitted',
            with: [
                'eventName' => $this->eventName,
                'leadFullName' => $this->leadFullName,
                'leadEmail' => $this->leadEmail,
                'leadPhone' => $this->leadPhone,
                'leadNotes' => $this->leadNotes,
                'interestedAssets' => $this->interestedAssets,
                'account' => $this->account,
                'tenantLabel' => $this->tenantLabel,
                'isOwnerCopy' => $this->isOwnerCopy,
            ],
        );
    }
}
