<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Contact\Models\Contact;
use App\Domain\Shipment\Models\Shipment;
use App\Models\AccountSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShipmentTrackingNotification extends Mailable implements ShouldQueue
{
    use SerializesModels;

    public function __construct(
        public Shipment $shipment,
        public AccountSettings $account,
        public ?Contact $contact,
        public string $trackUrl,
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->account->name ?? 'Your Service Provider';
        $tracking = $this->shipment->tracking_code ?? $this->shipment->display_name;

        return new Envelope(
            subject: "Shipment tracking — {$tracking} — {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shipment-tracking-notification',
            with: [
                'shipment' => $this->shipment,
                'account' => $this->account,
                'contact' => $this->contact,
                'trackUrl' => $this->trackUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
