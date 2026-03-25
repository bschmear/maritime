<?php

namespace App\Mail;

use App\Domain\Contract\Models\Contract;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractReviewRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Contract $contract;
    public AccountSettings $account;
    public string $reviewUrl;

    public function __construct(Contract $contract, AccountSettings $account, string $reviewUrl)
    {
        $this->contract = $contract;
        $this->account = $account;
        $this->reviewUrl = $reviewUrl;
    }

    public function envelope(): Envelope
    {
        $companyName = $this->contract->transaction?->subsidiary?->display_name
            ?? $this->account->name
            ?? 'Service Provider';

        return new Envelope(
            subject: "Signature Requested: Contract {$this->contract->contract_number} — {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contract-review-request',
            with: [
                'contract' => $this->contract,
                'account' => $this->account,
                'reviewUrl' => $this->reviewUrl,
                'totalAmount' => $this->contract->total_amount,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
