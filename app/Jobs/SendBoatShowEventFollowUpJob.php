<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\BoatShowEvent\Support\BoatShowFollowUpMergeData;
use App\Domain\BoatShowEvent\Support\BoatShowFollowUpMerger;
use App\Domain\BoatShowEvent\Support\TenantAccountOwnerSalesperson;
use App\Domain\Communication\Actions\CreateCommunication;
use App\Domain\EmailTemplate\Models\EmailTemplate;
use App\Domain\Lead\Models\Lead;
use App\Domain\User\Models\User;
use App\Enums\Communication\Channel;
use App\Enums\Communication\CommunicationType;
use App\Enums\Communication\Status;
use App\Mail\BoatShowEventFollowUpMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendBoatShowEventFollowUpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $boatShowEventId,
        public int $leadId,
    ) {}

    public function handle(CreateCommunication $createCommunication): void
    {
        $event = BoatShowEvent::query()->with('show:id,display_name')->find($this->boatShowEventId);
        if (! $event || ! $event->auto_followup) {
            return;
        }

        $lead = Lead::query()->find($this->leadId);
        if (! $lead || blank($lead->email)) {
            return;
        }

        $template = $this->resolveTemplate($event);
        if (! $template) {
            Log::info('Boat show follow-up skipped: no active email template', [
                'event_id' => $event->id,
                'lead_id' => $lead->id,
            ]);

            return;
        }

        $resolved = TenantAccountOwnerSalesperson::resolve();
        $centralAccount = $resolved['account'];
        $salesperson = $lead->assigned_user_id
            ? User::query()->find($lead->assigned_user_id)
            : null;
        if (! $salesperson) {
            $salesperson = $resolved['user'];
        }

        $assetIds = BoatShowFollowUpMergeData::assetIdsForSubmission($event, $lead);
        $mergeData = BoatShowFollowUpMergeData::forLeadAndEvent(
            $lead,
            $event,
            $centralAccount,
            $salesperson,
            $assetIds,
        );

        $subject = BoatShowFollowUpMerger::merge($template->email_subject, $mergeData);
        $htmlBody = BoatShowFollowUpMerger::merge($template->email_message, $mergeData);

        $ccEmails = $this->staffCcEmails($event, (string) $lead->email);

        $mail = new BoatShowEventFollowUpMail(
            mergedSubject: $subject,
            mergedHtmlBody: $htmlBody,
            replyToEmail: $salesperson->email ?: null,
            replyToName: trim((string) ($salesperson->display_name ?? '')) ?: null,
            ccAddresses: $ccEmails,
        );

        try {
            Mail::to((string) $lead->email)->send($mail);
        } catch (\Throwable $e) {
            Log::error('Boat show follow-up email failed to send', [
                'event_id' => $event->id,
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $notesPlain = trim(Str::limit(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $htmlBody)), 8000));

        try {
            $result = $createCommunication([
                'communicable_type' => 'Lead',
                'communicable_id' => $lead->id,
                'user_id' => $salesperson->id,
                'communication_type_id' => CommunicationType::Email->id(),
                'direction' => 'outbound',
                'subject' => Str::limit($subject, 255, ''),
                'notes' => "Boat show event follow-up email sent to {$lead->email}.\n\n".$notesPlain,
                'needs_follow_up' => false,
                'is_private' => false,
                'status_id' => Status::Closed->id(),
                'channel_id' => Channel::Email->id(),
                'priority_id' => 2,
                'tags' => ['boat_show_follow_up'],
                'date_contacted' => now()->toIso8601String(),
                'assigned_to' => $salesperson->id,
            ]);

            if (! ($result['success'] ?? false)) {
                Log::warning('Boat show follow-up sent but communication log failed', [
                    'event_id' => $event->id,
                    'lead_id' => $lead->id,
                    'message' => $result['message'] ?? 'unknown',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Boat show follow-up sent but communication log raised an exception', [
                'event_id' => $event->id,
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveTemplate(BoatShowEvent $event): ?EmailTemplate
    {
        $candidates = [];
        if ($event->email_template_id) {
            $candidates[] = EmailTemplate::query()->find($event->email_template_id);
        }
        $candidates[] = EmailTemplate::ensureBoatShowFollowUpSingleton();

        foreach ($candidates as $template) {
            if (
                $template
                && $template->type === EmailTemplate::TYPE_BOAT_SHOW_FOLLOWUP
                && $template->is_active
            ) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function staffCcEmails(BoatShowEvent $event, string $leadEmail): array
    {
        $ids = $event->recipients['user_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return [];
        }

        $leadEmailLower = strtolower($leadEmail);

        return User::query()
            ->whereIn('id', array_map('intval', $ids))
            ->pluck('email')
            ->filter(fn (mixed $e) => is_string($e) && $e !== '' && strtolower($e) !== $leadEmailLower)
            ->values()
            ->all();
    }
}
