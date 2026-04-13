<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactDemoRequest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{first_name: string, last_name: string, email: string, dealership_name: string, locations: string, message?: string|null}  $fields
     */
    public function __construct(
        public array $fields,
        public string $locationsLabel,
    ) {}

    public function envelope(): Envelope
    {
        $name = trim($this->fields['first_name'].' '.$this->fields['last_name']);

        return new Envelope(
            subject: 'Demo request: '.$this->fields['dealership_name'],
            replyTo: [
                new Address($this->fields['email'], $name !== '' ? $name : $this->fields['email']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-demo-request',
            with: [
                'fields' => $this->fields,
                'locationsLabel' => $this->locationsLabel,
            ],
        );
    }
}
