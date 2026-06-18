<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Enums\System\SystemLogAction;

class CancelDeniedDeliveryRequest
{
    public function __invoke(RecordModel $delivery): array
    {
        if ($delivery->review_decision !== ReviewDeliveryRequest::DECISION_DENIED) {
            return [
                'success' => false,
                'message' => 'Only denied delivery requests can be cancelled this way.',
                'record' => $delivery,
            ];
        }

        if ($delivery->status === 'cancelled') {
            return [
                'success' => false,
                'message' => 'This delivery request is already cancelled.',
                'record' => $delivery,
            ];
        }

        $userId = current_tenant_user_id();
        if ($userId === null) {
            return [
                'success' => false,
                'message' => 'You must be signed in to cancel this delivery request.',
                'record' => $delivery,
            ];
        }

        $isRequester = (int) $delivery->requested_by_user_id === (int) $userId;
        $isAdmin = current_tenant_role_slug() === 'admin';
        if (! $isRequester && ! $isAdmin) {
            return [
                'success' => false,
                'message' => 'Only the original requester can cancel this delivery request.',
                'record' => $delivery,
            ];
        }

        $delivery->update([
            'status' => 'cancelled',
            'pending_request' => false,
        ]);

        $record = $delivery->fresh(['location', 'requestedBy', 'reviewedBy', 'customer']);
        LogSystemEvent::record($record, SystemLogAction::Updated);

        return [
            'success' => true,
            'record' => $record,
        ];
    }
}
