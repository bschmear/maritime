<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Survey\Actions\SendSurveyInvitation;
use App\Domain\Survey\Models\SurveyInvitation;
use App\Enums\Surveys\InvitationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSurveyInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invitationId,
    ) {}

    public function handle(SendSurveyInvitation $sendSurveyInvitation): void
    {
        $invitation = SurveyInvitation::query()->find($this->invitationId);

        if (! $invitation || $invitation->status !== InvitationStatus::Scheduled) {
            return;
        }

        $result = $sendSurveyInvitation($invitation);

        if (! ($result['success'] ?? false)) {
            Log::warning('SendSurveyInvitationJob failed', [
                'invitation_id' => $this->invitationId,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
