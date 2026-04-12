<?php

namespace App\Mail;

use App\Domain\Estimate\Models\Estimate;
use App\Models\AccountSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously so “Send / resend for approval” delivers immediately without a queue worker.
 */
class EstimateApprovalRequest extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Estimate $estimate,
        public AccountSettings $account,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->account->name ?? 'Your Service Provider';

        return new Envelope(
            subject: "Estimate {$this->estimate->display_name} — Review & Approval Required — {$companyName}",
        );
    }

    public function content(): Content
    {
        $version = $this->estimate->primaryVersion;
        $subtotal = (float) ($version?->subtotal ?? 0);
        $tax = (float) ($version?->tax ?? 0);
        $total = (float) ($version?->total ?? 0);
        $taxRate = (float) ($this->estimate->tax_rate ?? $version?->tax_rate ?? 0);

        return new Content(
            view: 'emails.estimate-approval-request',
            with: [
                'estimate' => $this->estimate,
                'account' => $this->account,
                'reviewUrl' => $this->reviewUrl,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'taxRate' => $taxRate,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
