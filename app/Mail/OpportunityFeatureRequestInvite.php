<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Opportunity\Models\Opportunity;
use App\Models\AccountSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Secure link for customers to submit asset options (and optional add-ons) on an opportunity line.
 */
class OpportunityFeatureRequestInvite extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Opportunity $opportunity,
        public AccountSettings $account,
        public string $url,
        public string $assetLabel,
        public bool $includesAddons,
        public ?string $customerNote = null,
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->account->name ?? 'Your dealer';

        return new Envelope(
            subject: "Feature Request Form — {$this->opportunity->display_name} — {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.opportunity-feature-request-invite',
            with: [
                'opportunity' => $this->opportunity,
                'account' => $this->account,
                'url' => $this->url,
                'assetLabel' => $this->assetLabel,
                'includesAddons' => $this->includesAddons,
                'customerNote' => $this->customerNote,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
