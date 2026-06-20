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

class DeliveryRequestSubmittedMail extends Mailable implements ShouldQueue
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

        return new Envelope(
            subject: "Delivery request submitted — {$this->delivery->display_name} — {$name}",
        );
    }

    public function content(): Content
    {
        $domain = tenant()?->domains->first()?->domain;
        $deliveryUrl = $domain
            ? "https://{$domain}/deliveries/{$this->delivery->id}"
            : '#';

        $this->delivery->loadMissing(['customer', 'location', 'requestedBy']);

        return new Content(
            view: 'emails.delivery-request-submitted',
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
