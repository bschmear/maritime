<?php

namespace App\Mail;

use App\Domain\Delivery\Models\Delivery;
use App\Domain\User\Models\User;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeliveryRequestReviewedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Delivery $delivery,
        public AccountSettings $account,
        public User $notifyUser,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->account->name ?? 'Notification';
        $decision = match ($this->delivery->review_decision) {
            'approved' => 'Approved',
            'denied' => 'Denied',
            'reschedule_requested' => 'Reschedule requested',
            default => 'Updated',
        };

        return new Envelope(
            subject: "Delivery request {$decision} — {$this->delivery->display_name} — {$name}",
        );
    }

    public function content(): Content
    {
        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;
        $path = route('deliveries.show', ['delivery' => $this->delivery->id], false);
        $deliveryUrl = $domain ? 'https://'.$domain.$path : url($path);

        $this->delivery->loadMissing(['customer', 'location', 'reviewedBy']);

        return new Content(
            view: 'emails.delivery-request-reviewed',
            with: [
                'delivery' => $this->delivery,
                'account' => $this->account,
                'notifyUser' => $this->notifyUser,
                'deliveryUrl' => $deliveryUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
