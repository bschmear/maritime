<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\User\Models\User;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WarrantyClaimVendorApprovedCreator extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public WarrantyClaim $claim,
        public AccountSettings $account,
        public User $creator,
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->claim->display_name ?? ('Claim #'.$this->claim->id);

        return new Envelope(
            subject: "Warranty claim {$ref} approved by manufacturer",
        );
    }

    public function content(): Content
    {
        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;
        $internalPath = route('warrantyclaims.show', ['warrantyclaim' => $this->claim->id], false);
        $internalUrl = $domain ? 'https://'.$domain.$internalPath : url($internalPath);

        return new Content(
            view: 'emails.warranty-claim-vendor-approved-creator',
            with: [
                'claim' => $this->claim,
                'account' => $this->account,
                'creator' => $this->creator,
                'internalUrl' => $internalUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
