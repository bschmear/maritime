<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Contact\Models\Contact;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Models\AccountSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Staff-initiated email with links and steps for the customer portal.
 */
class ContactPortalLink extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Contact $contact,
        public AccountSettings $account,
        public string $loginUrl,
        public string $registerUrl,
        public bool $hasCustomerProfile,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your customer portal — '.$this->tenantSenderDisplayName(),
        );
    }

    public function content(): Content
    {
        $name = trim((string) ($this->contact->display_name ?? ''));
        if ($name === '') {
            $name = trim(implode(' ', array_filter([
                (string) $this->contact->first_name,
                (string) $this->contact->last_name,
            ])));
        }
        if ($name === '') {
            $name = trim((string) ($this->contact->company ?? ''));
        }

        return new Content(
            view: 'emails.contact-portal-link',
            with: [
                'contactName' => $name !== '' ? $name : null,
                'senderDisplayName' => $this->tenantSenderDisplayName(),
                'loginUrl' => $this->loginUrl,
                'registerUrl' => $this->registerUrl,
                'hasCustomerProfile' => $this->hasCustomerProfile,
                'logoUrl' => $this->account->logo_url,
                'platformAppName' => (string) config('app.name'),
                'platformAppUrl' => rtrim((string) config('app.url'), '/'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    /**
     * Customer-facing sender name: subsidiary first, then account business name — never {@see config('app.name')}.
     */
    private function tenantSenderDisplayName(): string
    {
        $fromSubsidiary = Subsidiary::query()
            ->where('inactive', false)
            ->orderBy('id')
            ->value('display_name');

        if (is_string($fromSubsidiary) && trim($fromSubsidiary) !== '') {
            return trim($fromSubsidiary);
        }

        $settings = is_array($this->account->settings) ? $this->account->settings : [];
        $business = trim((string) ($settings['business_name'] ?? ''));
        if ($business !== '') {
            return $business;
        }

        return 'Your service provider';
    }
}
