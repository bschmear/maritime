<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Survey\Models\Survey;
use App\Domain\Survey\Models\SurveyInvitation;
use App\Models\AccountSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SurveyInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Survey $survey,
        public SurveyInvitation $invitation,
        public string $surveyUrl,
        public string $tenantLabel,
        public ?string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Please complete: '.$this->survey->title,
        );
    }

    public function content(): Content
    {
        $account = AccountSettings::getCurrent();

        return new Content(
            view: 'emails.survey-invitation',
            with: [
                'surveyTitle' => $this->survey->title,
                'surveyDesc' => $this->survey->public_description ?: $this->survey->description,
                'surveyUrl' => $this->surveyUrl,
                'tenantLabel' => $this->tenantLabel,
                'recipientName' => $this->recipientName,
                'brandColor' => $this->survey->getEffectiveColor(),
                'logoUrl' => $account->logo_url,
            ],
        );
    }
}
