<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Enums\System\SystemLogAction;
use Illuminate\Support\Facades\Validator;

class ResubmitDeliveryRequest
{
    public function __invoke(RecordModel $delivery, ?string $scheduledAt = null): array
    {
        if (! $this->canResubmit($delivery)) {
            return [
                'success' => false,
                'message' => 'This delivery request cannot be resubmitted.',
                'record' => $delivery,
            ];
        }

        $userId = current_tenant_user_id();
        if ($userId === null) {
            return [
                'success' => false,
                'message' => 'You must be signed in to resubmit a delivery request.',
                'record' => $delivery,
            ];
        }

        $isRequester = (int) $delivery->requested_by_user_id === (int) $userId;
        $isAdmin = current_tenant_role_slug() === 'admin';
        if (! $isRequester && ! $isAdmin) {
            return [
                'success' => false,
                'message' => 'Only the original requester can resubmit this delivery request.',
                'record' => $delivery,
            ];
        }

        $effectiveScheduledAt = $scheduledAt ?? $delivery->proposed_scheduled_at ?? $delivery->scheduled_at;

        $validated = Validator::make([
            'scheduled_at' => $effectiveScheduledAt,
        ], [
            'scheduled_at' => 'required|date',
        ])->validate();

        $delivery->update([
            'pending_request' => true,
            'status' => 'requested',
            'scheduled_at' => $validated['scheduled_at'],
            'time_to_leave_by' => null,
            'proposed_scheduled_at' => null,
            'review_decision' => null,
            'review_notes' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'requested_at' => now(),
        ]);

        $delivery = $delivery->fresh(['location', 'requestedBy', 'customer']);
        LogSystemEvent::record($delivery, SystemLogAction::Updated);

        return [
            'success' => true,
            'record' => $delivery,
        ];
    }

    private function canResubmit(RecordModel $delivery): bool
    {
        if ($delivery->review_decision === ReviewDeliveryRequest::DECISION_DENIED) {
            return true;
        }

        if ($delivery->status === 'cancelled') {
            return false;
        }

        if ($delivery->pending_request) {
            return true;
        }

        return $delivery->review_decision === ReviewDeliveryRequest::DECISION_RESCHEDULE_REQUESTED;
    }
}
