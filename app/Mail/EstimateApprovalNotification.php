<?php

namespace App\Mail;

use App\Domain\Estimate\Models\Estimate;
use App\Domain\User\Models\User;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EstimateApprovalNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Estimate $estimate,
        public AccountSettings $account,
        public User $user,
        public string $action, // 'approved' | 'declined'
    ) {}

    public function envelope(): Envelope
    {
        $label       = $this->action === 'approved' ? 'Approved' : 'Declined';
        $accountName = $this->account->name ?? 'Notification';

        return new Envelope(
            subject: "Estimate {$this->estimate->display_name} {$label} — {$accountName}",
        );
    }

    public function content(): Content
    {
        $tenant     = tenant();
        $domain     = $tenant?->domains->first()?->domain;
        $estimateUrl = $domain
            ? "https://{$domain}/estimates/{$this->estimate->id}"
            : '#';

        return new Content(
            view: 'emails.estimate-approval-notification',
            with: [
                'estimate'    => $this->estimate,
                'account'     => $this->account,
                'user'        => $this->user,
                'action'      => $this->action,
                'estimateUrl' => $estimateUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
