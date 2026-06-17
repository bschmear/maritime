<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Exceptions\DeliveryFleetConflictException;
use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Support\DeliveryApproverResolver;
use App\Domain\Delivery\Support\DeliveryFleetConflictGuard;
use App\Domain\Delivery\Support\SyncTechnicianDeliveryInProgress;
use App\Domain\SystemLog\Support\LogSystemEvent;
use App\Enums\System\SystemLogAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ReviewDeliveryRequest
{
    public const DECISION_APPROVED = 'approved';

    public const DECISION_DENIED = 'denied';

    public const DECISION_RESCHEDULE_REQUESTED = 'reschedule_requested';

    public function __invoke(RecordModel $delivery, string $decision, ?string $notes = null, ?string $proposedScheduledAt = null): array
    {
        if ($delivery->status !== 'requested') {
            return [
                'success' => false,
                'message' => 'Only requested deliveries can be reviewed.',
                'record' => $delivery,
            ];
        }

        $delivery->loadMissing('location');
        if (! DeliveryApproverResolver::currentUserCanApprove($delivery->location)) {
            return [
                'success' => false,
                'message' => 'You are not authorized to review this delivery request.',
                'record' => $delivery,
            ];
        }

        $reviewerId = current_tenant_user_id();
        if ($reviewerId === null) {
            return [
                'success' => false,
                'message' => 'You must be signed in to review delivery requests.',
                'record' => $delivery,
            ];
        }

        $validator = Validator::make([
            'decision' => $decision,
            'review_notes' => $notes,
            'proposed_scheduled_at' => $proposedScheduledAt,
        ], [
            'decision' => 'required|in:'.self::DECISION_APPROVED.','.self::DECISION_DENIED.','.self::DECISION_RESCHEDULE_REQUESTED,
            'review_notes' => 'nullable|string|max:5000',
            'proposed_scheduled_at' => 'nullable|date|required_if:decision,'.self::DECISION_RESCHEDULE_REQUESTED,
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => $validator->errors()->first(),
                'record' => $delivery,
            ];
        }

        $validated = $validator->validated();
        $now = now();

        try {
            return DB::transaction(function () use ($delivery, $validated, $reviewerId, $now, $decision) {
                $updates = [
                    'reviewed_by_user_id' => $reviewerId,
                    'reviewed_at' => $now,
                    'review_decision' => $decision,
                    'review_notes' => $validated['review_notes'] ?? null,
                ];

                if ($decision === self::DECISION_APPROVED) {
                    $updates['status'] = 'scheduled';
                    $updates['proposed_scheduled_at'] = null;
                    $delivery->update($updates);

                    app(ComputeDeliveryTravelEstimates::class)($delivery);
                    $delivery->save();

                    $delivery = DeliveryFleetConflictGuard::assertResolved($delivery->fresh(), null);
                    SyncTechnicianDeliveryInProgress::recomputeForUserIds([$delivery?->technician_id]);

                    $delivery = $delivery->fresh(['location', 'requestedBy', 'reviewedBy', 'customer']);
                    LogSystemEvent::record($delivery, SystemLogAction::Updated);

                    return [
                        'success' => true,
                        'record' => $delivery,
                    ];
                }

                if ($decision === self::DECISION_DENIED) {
                    $updates['status'] = 'cancelled';
                    $updates['proposed_scheduled_at'] = null;
                    $delivery->update($updates);

                    $delivery = $delivery->fresh(['location', 'requestedBy', 'reviewedBy', 'customer']);
                    LogSystemEvent::record($delivery, SystemLogAction::Updated);

                    return [
                        'success' => true,
                        'record' => $delivery,
                    ];
                }

                $updates['proposed_scheduled_at'] = $validated['proposed_scheduled_at'] ?? null;
                $delivery->update($updates);

                $delivery = $delivery->fresh(['location', 'requestedBy', 'reviewedBy', 'customer']);
                LogSystemEvent::record($delivery, SystemLogAction::Updated);

                return [
                    'success' => true,
                    'record' => $delivery,
                ];
            });
        } catch (DeliveryFleetConflictException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'conflicts' => $e->conflicts,
                'record' => $delivery->fresh(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in ReviewDeliveryRequest', [
                'delivery_id' => $delivery->id,
                'decision' => $decision,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => $delivery,
            ];
        }
    }
}
