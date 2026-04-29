<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Customer\Models\Customer;
use App\Models\AccountSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * @param  list<array{label: string, url: string}>  $links
 */
class CustomerAssetSpecSheetShareMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Customer $customer,
        public AccountSettings $account,
        public array $links,
        public string $assetDisplayName,
    ) {}

    public function envelope(): Envelope
    {
        $business = is_array($this->account->settings)
            ? trim((string) ($this->account->settings['business_name'] ?? ''))
            : '';
        $from = $business !== '' ? $business : ($this->account->name ?? 'Your dealer');

        return new Envelope(
            subject: 'Specification sheet'.(count($this->links) > 1 ? 's' : '').' — '.$from,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-asset-spec-sheet-share',
            with: [
                'customer' => $this->customer,
                'account' => $this->account,
                'links' => $this->links,
                'logoUrl' => $this->account->logo_url,
                'greetingName' => trim((string) ($this->customer->display_name ?? '')),
                'assetDisplayName' => $this->assetDisplayName,
                'portalUrl' => route('portal.index'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
