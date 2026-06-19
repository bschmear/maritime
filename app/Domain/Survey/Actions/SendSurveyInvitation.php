<?php

declare(strict_types=1);

namespace App\Domain\Survey\Actions;

use App\Domain\Survey\Models\SurveyInvitation;
use App\Domain\User\Models\User as TenantUser;
use App\Enums\Surveys\InvitationStatus;
use App\Mail\SurveyInvitationMail;
use App\Models\User as WebUser;
use App\Services\Mail\TenantMailService;
use App\Services\SMS\SmsService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SendSurveyInvitation
{
    public function __construct(
        protected TenantMailService $tenantMail,
        protected SmsService $smsService,
    ) {}

    /**
     * @return array{success: bool, message?: string}
     */
    public function __invoke(SurveyInvitation $invitation): array
    {
        $invitation->loadMissing(['survey', 'contact']);

        if ($invitation->status !== InvitationStatus::Scheduled) {
            return ['success' => false, 'message' => 'Invitation is no longer scheduled.'];
        }

        $survey = $invitation->survey;
        if (! $survey || ! $survey->status) {
            $this->markFailed($invitation, 'Survey is not active.');

            return ['success' => false, 'message' => 'Survey is not active.'];
        }

        $agentId = $invitation->assigned_to_user_id ?? $invitation->sent_by_user_id;
        $surveyUrl = $survey->signedRecipientShowUrl(
            $this->signedTypeForInvitation($invitation),
            $this->signedIdForInvitation($invitation),
            $agentId,
        );

        $tenantLabel = tenant()?->name ?? (string) config('app.name', 'Your team');
        $errors = [];
        $tenantSender = $invitation->sent_by_user_id
            ? TenantUser::query()->find($invitation->sent_by_user_id)
            : null;
        $mailActor = $this->resolveMailActor($invitation, $tenantSender);
        $smsActor = Auth::user() ?? $tenantSender;

        if ($invitation->send_email) {
            try {
                $mailable = new SurveyInvitationMail(
                    $survey,
                    $invitation,
                    $surveyUrl,
                    $tenantLabel,
                    $invitation->contact?->display_name,
                );
                $this->tenantMail->send($invitation->recipient_email, $mailable, $mailActor);
            } catch (\Throwable $e) {
                Log::error('Survey invitation email failed', [
                    'invitation_id' => $invitation->id,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = 'Email failed: '.$e->getMessage();
            }
        }

        if ($invitation->send_sms && $smsActor) {
            $result = $this->smsService->sendSurveyInvitationSms(
                $smsActor,
                $invitation->contact,
                $survey,
                $surveyUrl,
                $invitation->recipient_mobile,
            );
            if (! $result->success) {
                $errors[] = 'SMS failed: '.($result->error ?? 'Unknown error');
            }
        }

        if ($invitation->send_email && ! empty($errors) && str_contains($errors[0], 'Email failed')) {
            $this->markFailed($invitation, implode(' ', $errors));

            return ['success' => false, 'message' => implode(' ', $errors)];
        }

        $invitation->update([
            'status' => InvitationStatus::Sent,
            'sent_at' => now(),
            'error_message' => $errors !== [] ? implode(' ', $errors) : null,
        ]);

        return [
            'success' => true,
            'message' => $errors === []
                ? 'Survey invitation sent.'
                : 'Survey invitation sent with warnings: '.implode(' ', $errors),
        ];
    }

    protected function signedTypeForInvitation(SurveyInvitation $invitation): string
    {
        return match ($invitation->record_type) {
            'lead' => 'lead',
            default => 'contact',
        };
    }

    protected function signedIdForInvitation(SurveyInvitation $invitation): int
    {
        if ($invitation->record_type === 'lead') {
            return (int) $invitation->record_id;
        }

        return (int) $invitation->contact_id;
    }

    protected function markFailed(SurveyInvitation $invitation, string $message): void
    {
        $invitation->update([
            'status' => InvitationStatus::Failed,
            'error_message' => $message,
        ]);
    }

    /**
     * TenantMailService sandbox routing expects the central (web) user, not the tenant users row.
     */
    protected function resolveMailActor(SurveyInvitation $invitation, ?TenantUser $tenantSender): ?Authenticatable
    {
        $auth = Auth::user();
        if ($auth instanceof WebUser) {
            return $auth;
        }

        $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

        if ($invitation->sent_by_web_user_id) {
            $fromId = WebUser::on($centralConnection)->find($invitation->sent_by_web_user_id);
            if ($fromId instanceof WebUser) {
                return $fromId;
            }
        }

        $email = trim((string) ($tenantSender?->email ?? ''));
        if ($email === '') {
            return null;
        }

        return WebUser::on($centralConnection)->where('email', $email)->first();
    }
}
