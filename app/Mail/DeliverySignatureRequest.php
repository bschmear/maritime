<?php

namespace App\Mail;

use App\Domain\Delivery\Models\Delivery;
use App\Models\AccountSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when staff requests customer signature on a delivery (review link).
 */
class DeliverySignatureRequest extends Mailable implements ShouldQueue
{
    use SerializesModels;

    public function __construct(
        public Delivery $delivery,
        public AccountSettings $account,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->account->name ?? 'Your Service Provider';

        return new Envelope(
            subject: "Delivery {$this->delivery->display_name} — Review & Sign — {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.delivery-signature-request',
            with: [
                'delivery' => $this->delivery,
                'account' => $this->account,
                'reviewUrl' => $this->reviewUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
