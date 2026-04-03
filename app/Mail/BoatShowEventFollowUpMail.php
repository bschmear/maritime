<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoatShowEventFollowUpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $ccAddresses
     */
    public function __construct(
        public string $mergedSubject,
        public string $mergedHtmlBody,
        public ?string $replyToEmail,
        public ?string $replyToName,
        public array $ccAddresses = [],
    ) {}

    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: $this->mergedSubject,
        );

        if ($this->replyToEmail) {
            $envelope->replyTo(new Address(
                $this->replyToEmail,
                $this->replyToName ?: '',
            ));
        }

        if ($this->ccAddresses !== []) {
            $envelope->cc($this->ccAddresses);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.boat-show-event-follow-up',
            with: [
                'htmlBody' => $this->mergedHtmlBody,
            ],
        );
    }
}
