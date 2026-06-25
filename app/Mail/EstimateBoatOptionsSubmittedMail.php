<?php

namespace App\Mail;

use App\Domain\Estimate\Models\Estimate;
use App\Domain\Estimate\Models\EstimateCustomerOptionSignoff;
use App\Domain\Estimate\Models\EstimateLineItem;
use App\Domain\User\Models\User;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EstimateBoatOptionsSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Estimate $estimate,
        public EstimateCustomerOptionSignoff $signoff,
        public EstimateLineItem $lineItem,
        public AccountSettings $account,
        public User $notifyUser,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->account->name ?? 'Notification';

        return new Envelope(
            subject: "Boat options submitted — {$this->estimate->display_name} — {$name}",
        );
    }

    public function content(): Content
    {
        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;
        $path = route('estimates.show', ['estimate' => $this->estimate->id], false);
        $estimateUrl = $domain ? 'https://'.$domain.$path : url($path);

        $lineLabel = trim($this->lineItem->name ?: 'Boat');
        $lineNumber = ((int) $this->lineItem->position) + 1;

        return new Content(
            view: 'emails.estimate-boat-options-submitted',
            with: [
                'estimate' => $this->estimate,
                'signoff' => $this->signoff,
                'lineItem' => $this->lineItem,
                'account' => $this->account,
                'notifyUser' => $this->notifyUser,
                'estimateUrl' => $estimateUrl,
                'lineLabel' => $lineLabel,
                'lineNumber' => $lineNumber,
                'selections' => $this->lineItem->selectedAssetOptions ?? collect(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
