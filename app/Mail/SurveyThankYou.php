<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Survey\Models\Survey;
use App\Domain\Survey\Models\SurveyResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the respondent after they submit a survey (when an email was collected).
 */
class SurveyThankYou extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Survey $survey,
        public SurveyResponse $response,
        public string $tenantLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thank you — '.$this->survey->title,
        );
    }

    public function content(): Content
    {
        $body = $this->survey->thank_you_message
            ?: 'Thank you for completing this survey. We appreciate your feedback.';

        $submittedAt = $this->response->submitted_at
            ? \Carbon\Carbon::parse($this->response->submitted_at)->format('F j, Y \a\t g:i A')
            : now()->format('F j, Y \a\t g:i A');

        $answeredCount = $this->response->answers()->count();

        return new Content(
            view: 'emails.survey-thank-you',
            with: [
                'surveyTitle'    => $this->survey->title,
                'surveyDesc'     => $this->survey->public_description ?: null,
                'body'           => $body,
                'respondentName' => $this->respondentName(),
                'tenantLabel'    => $this->tenantLabel,
                'submittedAt'    => $submittedAt,
                'answeredCount'  => $answeredCount,
                'brandColor'     => config('app.app_brand', '#2663eb'),
            ],
        );
    }

    protected function respondentName(): string
    {
        $name = trim((string) ($this->response->first_name ?? '').' '.(string) ($this->response->last_name ?? ''));

        return $name !== '' ? $name : 'there';
    }
}
