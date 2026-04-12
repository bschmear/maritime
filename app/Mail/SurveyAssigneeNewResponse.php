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
 * Email alert for the user assigned to the survey response (creator or agent).
 */
class SurveyAssigneeNewResponse extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Survey $survey,
        public SurveyResponse $response,
        public string $tenantLabel,
        public string $responseUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New survey response: '.$this->survey->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.survey-assignee-new-response',
            with: [
                'surveyTitle' => $this->survey->title,
                'respondentLine' => $this->respondentLine(),
                'responseUrl' => $this->responseUrl,
                'tenantLabel' => $this->tenantLabel,
            ],
        );
    }

    protected function respondentLine(): string
    {
        $name = trim((string) ($this->response->first_name ?? '').' '.(string) ($this->response->last_name ?? ''));
        $email = (string) ($this->response->email ?? '');

        if ($name !== '' && $email !== '') {
            return "{$name} ({$email})";
        }
        if ($name !== '') {
            return $name;
        }
        if ($email !== '') {
            return $email;
        }

        return 'Anonymous respondent';
    }
}
