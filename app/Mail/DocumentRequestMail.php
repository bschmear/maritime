<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Contact\Models\Contact;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contact $contact,
        public AccountSettings $account,
        public DocumentRequest $documentRequest,
        public string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Document requested — '.$this->documentRequest->title,
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

        return new Content(
            view: 'emails.document-request',
            with: [
                'contactName' => $name !== '' ? $name : null,
                'senderDisplayName' => $this->tenantSenderDisplayName(),
                'title' => $this->documentRequest->title,
                'description' => $this->documentRequest->description,
                'portalUrl' => $this->portalUrl,
                'logoUrl' => $this->account->logo_url,
            ],
        );
    }

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

        return $business !== '' ? $business : (string) config('app.name');
    }
}
