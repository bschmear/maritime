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

/**
 * Immediate "new boat show lead" alert (boat-show-lead-submitted view).
 * Sent to the event's Notify users (recipients.user_ids), or the central account owner if none.
 * Opt out with config('boat_show.send_immediate_owner_lead_notification') = false.
 * Visitor templated outreach: {@see SendBoatShowEventFollowUpJob} (boat_show_event_followup).
 */
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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New boat show lead: {$this->leadFullName} — {$this->eventName}",
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
            ],
        );
    }
}
