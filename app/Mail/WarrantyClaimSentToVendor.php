<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Contact\Models\Contact;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WarrantyClaimSentToVendor extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public WarrantyClaim $claim,
        public AccountSettings $account,
        public Contact $contact,
        public string $reviewUrl,
        public string $vendorPortalLoginUrl,
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->claim->display_name ?? ('Claim #'.$this->claim->id);
        $accountName = $this->account->name ?? 'Warranty';

        return new Envelope(
            subject: "Warranty claim {$ref} — {$accountName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.warranty-claim-sent-to-vendor',
            with: [
                'claim' => $this->claim,
                'account' => $this->account,
                'contact' => $this->contact,
                'reviewUrl' => $this->reviewUrl,
                'vendorPortalLoginUrl' => $this->vendorPortalLoginUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
