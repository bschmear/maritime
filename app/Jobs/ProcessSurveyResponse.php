<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Communication\Actions\CreateCommunication;
use App\Domain\Contact\Models\Contact;
use App\Domain\Notification\Actions\CreateNotification;
use App\Domain\Survey\Models\Survey;
use App\Domain\Survey\Models\SurveyResponse;
use App\Domain\User\Models\User;
use App\Enums\Communication\Channel;
use App\Enums\Communication\CommunicationType;
use App\Enums\Communication\Status;
use App\Mail\SurveyAssigneeNewResponse;
use App\Mail\SurveyThankYou;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * After a public survey is submitted: thank-you email to the respondent (if email known),
 * email + in-app notification for the assigned user (survey owner or agent).
 */
class ProcessSurveyResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Survey $survey,
        public SurveyResponse $response,
    ) {}

    public function handle(
        CreateNotification $createNotification,
        CreateCommunication $createCommunication,
    ): void {
        $survey = $this->survey->fresh();
        $response = $this->response->fresh();

        if (! $survey || ! $response) {
            Log::warning('ProcessSurveyResponse: missing survey or response after refresh');

            return;
        }

        $tenantLabel = tenant()?->name ?? (string) config('app.name', 'Your team');

        $rawEmail = $response->email ? trim((string) $response->email) : '';
        $respondentEmail = $rawEmail !== '' ? filter_var($rawEmail, FILTER_VALIDATE_EMAIL) : false;
        if (is_string($respondentEmail)) {
            try {
                Mail::to($respondentEmail)->send(new SurveyThankYou($survey, $response, $tenantLabel));
            } catch (\Throwable $e) {
                Log::error('Survey thank-you email failed', [
                    'survey_id' => $survey->id,
                    'response_id' => $response->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->logSurveyCommunicationForMatchingContact(
                $createCommunication,
                $survey,
                $response,
                $respondentEmail,
            );
        }

        $assignee = User::query()->find($response->assigned_to);
        if (! $assignee) {
            return;
        }

        $responseUrl = $this->tenantSurveyResponseShowUrl($survey, $response);

        $assigneeAddr = trim((string) $assignee->email);
        $assigneeEmail = $assigneeAddr !== '' ? filter_var($assigneeAddr, FILTER_VALIDATE_EMAIL) : false;
        if (is_string($assigneeEmail)) {
            try {
                Mail::to($assigneeEmail)->send(
                    new SurveyAssigneeNewResponse($survey, $response, $tenantLabel, $responseUrl),
                );
            } catch (\Throwable $e) {
                Log::error('Survey assignee notification email failed', [
                    'survey_id' => $survey->id,
                    'response_id' => $response->id,
                    'assignee_id' => $assignee->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = sprintf(
            'New response on "%s" from %s.',
            $survey->title,
            $this->respondentSummary($response),
        );

        $result = $createNotification([
            'assigned_to_user_id' => $assignee->id,
            'type' => 'survey_response',
            'title' => 'New survey response',
            'message' => $message,
            'route' => 'surveyResponseShow',
            'route_params' => [
                'sid' => $survey->uuid,
                'rid' => $response->id,
            ],
        ]);

        if (! ($result['success'] ?? false)) {
            Log::error('ProcessSurveyResponse: CreateNotification failed', [
                'survey_id' => $survey->id,
                'response_id' => $response->id,
                'detail' => $result['message'] ?? 'unknown',
            ]);
        }
    }

    protected function logSurveyCommunicationForMatchingContact(
        CreateCommunication $createCommunication,
        Survey $survey,
        SurveyResponse $response,
        string $respondentEmail,
    ): void {
        $contact = Contact::findByEmailCaseInsensitive($respondentEmail);
        if (! $contact) {
            return;
        }

        $this->ensureSourceableContact($response, $contact);

        $actorUserId = (int) ($response->assigned_to ?: $survey->user_id);
        if (! User::query()->whereKey($actorUserId)->exists()) {
            $actorUserId = (int) $survey->user_id;
        }
        if (! User::query()->whereKey($actorUserId)->exists()) {
            Log::warning('ProcessSurveyResponse: cannot log communication, no valid user_id', [
                'survey_id' => $survey->id,
                'response_id' => $response->id,
                'contact_id' => $contact->id,
            ]);

            return;
        }

        $responseUrl = $this->tenantSurveyResponseShowUrl($survey, $response);

        $notes = sprintf(
            "Submitted survey \"%s\" (response #%d).\n\nView in app: %s",
            $survey->title,
            $response->id,
            $responseUrl,
        );

        try {
            $result = $createCommunication([
                'communicable_type' => 'Contact',
                'communicable_id' => $contact->id,
                'user_id' => $actorUserId,
                'communication_type_id' => CommunicationType::SurveySubmission->id(),
                'direction' => 'inbound',
                'subject' => 'Survey: '.$survey->title,
                'notes' => $notes,
                'needs_follow_up' => false,
                'is_private' => false,
                'status_id' => Status::Closed->id(),
                'channel_id' => Channel::Survey->id(),
                'priority_id' => 2,
                'tags' => ['survey_submission'],
                'date_contacted' => now()->toIso8601String(),
                'assigned_to' => $response->assigned_to ?: null,
            ]);

            if (! ($result['success'] ?? false)) {
                Log::warning('ProcessSurveyResponse: contact survey communication log failed', [
                    'survey_id' => $survey->id,
                    'response_id' => $response->id,
                    'contact_id' => $contact->id,
                    'message' => $result['message'] ?? 'unknown',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('ProcessSurveyResponse: contact survey communication raised an exception', [
                'survey_id' => $survey->id,
                'response_id' => $response->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function ensureSourceableContact(SurveyResponse $response, Contact $contact): void
    {
        if ($response->sourceable_id) {
            return;
        }

        $response->update([
            'sourceable_type' => Contact::class,
            'sourceable_id' => $contact->id,
        ]);
    }

    protected function respondentSummary(SurveyResponse $response): string
    {
        $name = trim((string) ($response->first_name ?? '').' '.(string) ($response->last_name ?? ''));
        $email = (string) ($response->email ?? '');

        if ($name !== '') {
            return $email !== '' ? "{$name} ({$email})" : $name;
        }

        return $email !== '' ? $email : 'a respondent';
    }

    /**
     * Tenant routes are not registered during queue/console (no tenant subdomain on the request),
     * so named routes like surveyResponseShow are missing. Build the same path as routes/tenant.php.
     */
    protected function tenantSurveyResponseShowUrl(Survey $survey, SurveyResponse $response): string
    {
        $domain = tenant()?->domains->first()?->domain;
        if ($domain === null || $domain === '') {
            return '#';
        }

        $query = http_build_query([
            'sid' => $survey->uuid,
            'rid' => $response->id,
        ]);

        return 'https://'.$domain.'/surveys/survey/response?'.$query;
    }
}
