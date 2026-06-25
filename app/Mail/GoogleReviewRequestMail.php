<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Subsidiary\Models\Subsidiary;
use App\Models\AccountSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoogleReviewRequestMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public AccountSettings $account,
        public Subsidiary $subsidiary,
        public string $customerName,
        public string $googleReviewUrl,
        public string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'How did we do? — '.$this->senderDisplayName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.google-review-request',
            with: [
                'customerName' => $this->customerName !== '' ? $this->customerName : null,
                'senderDisplayName' => $this->senderDisplayName(),
                'message' => $this->message,
                'googleReviewUrl' => $this->googleReviewUrl,
                'logoUrl' => $this->subsidiary->logo_url ?? $this->account->logo_url,
                'platformAppName' => (string) config('app.name'),
                'platformAppUrl' => rtrim((string) config('app.url'), '/'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function senderDisplayName(): string
    {
        $name = trim((string) ($this->subsidiary->display_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $settings = is_array($this->account->settings) ? $this->account->settings : [];
        $business = trim((string) ($settings['business_name'] ?? ''));

        return $business !== '' ? $business : 'Your service provider';
    }
}
