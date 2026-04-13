<?php

namespace App\Mail;

use App\Domain\Invoice\Models\Invoice;
use App\Models\AccountSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously when staff emails a customer the public invoice link.
 */
class InvoiceViewRequest extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public AccountSettings $account,
        public string $viewUrl,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->account->settings['business_name'] ?? config('app.name', 'Your service provider');

        return new Envelope(
            subject: 'Invoice '.$this->invoice->display_name.' — '.$company,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-view-request',
            with: [
                'invoice' => $this->invoice,
                'account' => $this->account,
                'viewUrl' => $this->viewUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
