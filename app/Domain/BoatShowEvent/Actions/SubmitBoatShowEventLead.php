<?php

declare(strict_types=1);

namespace App\Domain\BoatShowEvent\Actions;

use App\Domain\BoatShow\Models\BoatShowLead;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\BoatShowEvent\Support\BoatShowFollowUpScheduler;
use App\Domain\BoatShowEvent\Support\TenantAccountOwnerSalesperson;
use App\Domain\Lead\Actions\CreateLead;
use App\Domain\Lead\Models\Lead;
use App\Domain\Score\Actions\CreateScore;
use App\Enums\Entity\Source;
use App\Enums\Leads\Priority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitBoatShowEventLead
{
    public function __construct(
        private CreateLead $createLead,
        private CreateScore $createScore,
    ) {}

    /**
     * @param  array{first_name: string, last_name: string, email?: ?string, phone?: ?string, notes?: ?string, has_trade_in?: bool, marketing_opt_in?: bool, asset_ids?: array<int>}  $data
     * @return array{success: bool, lead_id?: int, message?: string}
     */
    public function __invoke(BoatShowEvent $event, array $data): array
    {
        $validated = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'has_trade_in' => ['sometimes', 'boolean'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
            'asset_ids' => ['nullable', 'array'],
            'asset_ids.*' => ['integer'],
        ])->validate();

        $validAssetIds = $event->eventAssets()->pluck('asset_id')->map(fn ($id) => (int) $id)->all();
        $requestedAssetIds = array_map('intval', $validated['asset_ids'] ?? []);
        foreach ($requestedAssetIds as $aid) {
            if (! in_array($aid, $validAssetIds, true)) {
                throw ValidationException::withMessages([
                    'asset_ids' => ['One or more selected assets are not part of this event.'],
                ]);
            }
        }

        $resolved = TenantAccountOwnerSalesperson::resolve();
        $salesperson = $resolved['user'];
        $eventLabel = $event->display_name ?? 'Boat show event';
        $contextLine = "Boat show: {$eventLabel} (event UUID: {$event->uuid})";

        $notesParts = array_filter([
            $validated['notes'] ?? null,
            $contextLine,
        ]);
        $combinedNotes = implode("\n\n", $notesParts);

        $leadPayload = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'notes' => $combinedNotes,
            'source_id' => Source::BoatShow->id(),
            'priority_id' => Priority::Medium->id(),
            'campaign' => $eventLabel,
            'medium' => 'boat_show_event',
            'source_details' => $event->uuid,
            'assigned_user_id' => $salesperson->id,
            'has_trade_in' => (bool) ($validated['has_trade_in'] ?? false),
            'marketing_opt_in' => (bool) ($validated['marketing_opt_in'] ?? false),
        ];

        $breakdown = [
            ['component' => 'boat_show_lead', 'value' => 25],
        ];

        if (! empty($validated['email'])) {
            $breakdown[] = ['component' => 'has_email', 'value' => 10];
        }

        if (! empty($validated['phone'])) {
            $breakdown[] = ['component' => 'has_phone', 'value' => 8];
        }

        if (! empty($requestedAssetIds)) {
            $breakdown[] = ['component' => 'selected_assets', 'value' => 10];
        }

        if (! empty($validated['marketing_opt_in'])) {
            $breakdown[] = ['component' => 'marketing_opt_in', 'value' => 5];
        }

        $totalScore = array_sum(array_column($breakdown, 'value'));

        $result = DB::transaction(function () use ($leadPayload, $requestedAssetIds, $event, $salesperson, $eventLabel, $totalScore) {
            $leadResult = ($this->createLead)($leadPayload);
            if (! ($leadResult['success'] ?? false) || ! isset($leadResult['record'])) {
                throw new \RuntimeException($leadResult['message'] ?? 'Could not create lead.');
            }

            /** @var Lead $lead */
            $lead = $leadResult['record'];

            BoatShowLead::query()->create([
                'boat_show_id' => $event->boat_show_id,
                'boat_show_event_id' => $event->id,
                'leadable_type' => $lead->getMorphClass(),
                'leadable_id' => $lead->getKey(),
                'captured_at' => now(),
                'meta' => [
                    'source' => 'public_lead_form',
                    'asset_ids' => $requestedAssetIds,
                ],
            ]);

            $scoreResult = ($this->createScore)([
                'scorable_type' => Lead::class,
                'scorable_id' => $lead->getKey(),
                'user_id' => $salesperson->id,
                'score_type' => 'manual',
                'meta' => [
                    'breakdown' => [
                        ['component' => 'boat_show_lead', 'value' => $totalScore],
                    ],
                    'reason' => 'Public boat show lead form submission',
                    'auto_generated' => true,
                    'event_id' => $event->id,
                ],
                'notes' => Str::limit('Boat show: '.$eventLabel, 250, ''),
            ]);
            if (! ($scoreResult['success'] ?? false)) {
                throw new \RuntimeException($scoreResult['message'] ?? 'Could not create score.');
            }

            return [
                'success' => true,
                'lead_id' => $lead->id,
            ];
        });

        if (($result['success'] ?? false) && isset($result['lead_id'])) {
            $lead = Lead::query()->find($result['lead_id']);
            if ($lead) {
                BoatShowFollowUpScheduler::scheduleIfApplicable($event, $lead);
            }
        }

        return $result;
    }
}
