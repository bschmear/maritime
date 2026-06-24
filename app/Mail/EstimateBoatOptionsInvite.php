<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Estimate\Models\Estimate;
use App\Mail\Concerns\RepliesToEstimateSalesperson;
use App\Models\AccountSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Secure links for customers to choose boat options on one or more estimate lines.
 */
class EstimateBoatOptionsInvite extends Mailable implements ShouldQueue
{
    use RepliesToEstimateSalesperson;
    use SerializesModels;

    /**
     * @param  array<int, array{label: string, url: string}>  $lines
     */
    public function __construct(
        public Estimate $estimate,
        public AccountSettings $account,
        public array $lines,
        public ?string $customMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->account->name ?? 'Your Service Provider';

        return new Envelope(
            subject: "Boat options — {$this->estimate->display_name} — {$companyName}",
            replyTo: $this->replyToSalespersonOnEstimate(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.estimate-boat-options-invite',
            with: [
                'estimate' => $this->estimate,
                'account' => $this->account,
                'lines' => $this->lines,
                'customMessage' => $this->customMessage,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
