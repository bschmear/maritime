<?php

namespace App\Mail;

use App\Domain\Contract\Models\Contract;
use App\Domain\User\Models\User;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractSignedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Contract $contract;
    public AccountSettings $account;
    public User $user;

    public function __construct(Contract $contract, AccountSettings $account, User $user)
    {
        $this->contract = $contract;
        $this->account = $account;
        $this->user = $user;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Contract Signed — {$this->contract->contract_number}",
        );
    }

    public function content(): Content
    {
        $domain = tenant()->domains->first()?->domain;
        $contractUrl = $domain
            ? "https://{$domain}/contracts/{$this->contract->id}"
            : '#';

        return new Content(
            view: 'emails.contract-signed-notification',
            with: [
                'contract' => $this->contract,
                'account' => $this->account,
                'user' => $this->user,
                'contractUrl' => $contractUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
