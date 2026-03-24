<?php

namespace App\Domain\Communication;

use App\Domain\Communication\Models\Communication;
use App\Domain\User\Models\User;
// use App\Models\Googl;
// use App\Services\Outlook;
use App\Enums\Communication\CommunicationType;
use App\Enums\Communication\NextActionType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Actions
{
    /**
     * Create a new communication record
     */
    public static function create(User $user, Team $team, array $validated): Communication
    {

        // Handle calendar event creation
        $calendarData = self::handleCalendarEventCreation($user, $team, $validated);

        // Process date fields
        if (isset($validated['next_action_at'])) {
            $validated['next_action_at'] = Carbon::parse($validated['next_action_at'])
                ->setTimezone('UTC')
                ->format('Y-m-d H:i:s');
        }

        if (isset($validated['date_contacted'])) {
            $validated['date_contacted'] = Carbon::parse($validated['date_contacted'])
                ->setTimezone('UTC')
                ->format('Y-m-d H:i:s');
        } else {
            $validated['date_contacted'] = Carbon::now('UTC')->format('Y-m-d H:i:s');
        }

        // Get the related record
        $record = self::getRelatedRecord($validated['communicable_type'], $validated['communicable_id'], $team);

        // Prepare communication data - using ID-based fields
        $communicationData = [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'communication_type_id' => $validated['communication_type_id'],
            'direction' => $validated['direction'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_private' => $validated['is_private'] ?? false,
            'status_id' => $validated['status_id'],
            'channel_id' => $validated['channel_id'] ?? null,
            'priority_id' => $validated['priority_id'],
            'tags' => $validated['tags'] ?? null,
            'outcome_id' => $validated['outcome_id'] ?? null,
            'next_action_type_id' => $validated['next_action_type_id'] ?? null,
            'next_action_at' => $validated['next_action_at'] ?? null,
            'date_contacted' => $validated['date_contacted'],
            'assigned_to' => $validated['assigned_to'] ?? null,
        ];

        // Add calendar info if event was created
        if ($calendarData) {
            $communicationData['calendar_id'] = $calendarData['calendar_id'];
            $communicationData['event_id'] = $calendarData['event_id'];
        }

        return $record->communications()->create($communicationData);
    }

    /**
     * Update an existing communication record
     */
    public static function update(User $user, Team $team, Communication $communication, array $validated): Communication
    {
        // Process date fields
        if (isset($validated['next_action_at'])) {
            $validated['next_action_at'] = Carbon::parse($validated['next_action_at'])
                ->setTimezone('UTC')
                ->format('Y-m-d H:i:s');
        }

        if (isset($validated['date_contacted'])) {
            $validated['date_contacted'] = Carbon::parse($validated['date_contacted'])
                ->setTimezone('UTC')
                ->format('Y-m-d H:i:s');
        }

        // Handle Google Calendar event management
        self::handleCalendarEventUpdate($user, $team, $communication, $validated);

        $communication->update($validated);

        return $communication->fresh()->load('user');
    }

    /**
     * Delete a communication record
     */
    public static function delete(User $user, Team $team, Communication $communication): array
    {
        // Authorization check
        if (! $user->hasAccessToCurrentTeam() || $communication->team_id != $team->id) {
            return [
                'success' => false,
                'message' => 'Unauthorized action.',
                'status_code' => 403,
            ];
        }

        try {
            // Remove Google Calendar event if it exists
            if (! empty($communication->event_id)) {
                self::removeCalendarEvent($user, $team, $communication->event_id);
            }

            $deleted = $communication->delete();

            if ($deleted) {
                return [
                    'success' => true,
                    'message' => 'Communication deleted successfully.',
                    'status_code' => 200,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete communication.',
                    'status_code' => 500,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete communication', [
                'error' => $e->getMessage(),
                'communication_id' => $communication->id,
                'user_id' => $user->id,
                'team_id' => $team->id,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete communication. Please try again.',
                'status_code' => 500,
            ];
        }
    }

    public static function destroySelected($request)
    {
        $currentTeam = currentTeam();
        if (! $currentTeam) {
            return response()->json(['success' => false, 'message' => 'Current team not found.']);
        }

        $user = auth()->user();
        $team = $currentTeam;

        if (! $user->hasAccessToCurrentTeam()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $inputs = $request->selectInput;

        $communications = $team->activities()->find($inputs);

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($communications as $communication) {
            $result = self::delete($user, $team, $communication);

            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
                $errors[] = "Communication ID {$communication->id}: {$result['message']}";
            }
        }

        if ($failedCount === 0) {
            $message = $successCount === 1
                ? 'Communication successfully deleted.'
                : "{$successCount} communications successfully deleted.";

            return response()->json(['success' => true, 'message' => $message]);
        } elseif ($successCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete communications: '.implode(', ', $errors),
            ], 400);
        } else {
            return response()->json([
                'success' => true,
                'message' => "{$successCount} communications deleted successfully, {$failedCount} failed.",
                'partial' => true,
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Handle calendar event creation for new communications
     */
    private static function handleCalendarEventCreation(User $user, Team $team, array $validated): ?array
    {
        if (! isset($validated['next_action_at'])) {
            return null;
        }

        $calendar = $user->calendars()->where('team_id', $team->id)->first();

        if (! $calendar || ! $calendar->third_party_cal) {
            return null;
        }

        if ($calendar && $calendar->third_party_cal) {
            switch ($calendar->cal_type) {
                case 'google':
                    $integration = $calendar->googleIntegration;
                    $cal_id = $integration?->external_id;
                    break;

                case 'outlook':
                    $integration = $calendar->outlookIntegration;
                    $cal_id = $integration?->external_id;
                    break;
            }
        }

        try {
            // Parse the next_action_at datetime and convert to user's timezone
            $next_action_datetime = Carbon::parse($validated['next_action_at'])->setTimezone($user->timezone);

            // Get communication type label for event title
            $typeLabel = 'Communication';
            if (isset($validated['communication_type_id'])) {
                $communicationType = CommunicationType::fromId($validated['communication_type_id']);
                if ($communicationType) {
                    $typeLabel = $communicationType->label();
                }
            }

            // Prepare event details
            $title = $validated['subject'] ?? 'Follow-up: '.$typeLabel;
            $start = $next_action_datetime->toISOString();
            $reminders = 2;
            $reminder_time = 30;

            // Build description
            $actionTypeLabel = 'Follow-up';
            if (isset($validated['next_action_type_id'])) {
                $nextActionType = NextActionType::fromId($validated['next_action_type_id']);
                if ($nextActionType) {
                    $actionTypeLabel = $nextActionType->label();
                }
            }

            $description = $actionTypeLabel;
            if (! empty($validated['notes'])) {
                $description .= ': '.$validated['notes'];
            }

            if ($calendar && $calendar->third_party_cal) {
                try {
                    if ($calendar->cal_type == 'google') {
                        $events = Googl::CreateEvent(
                            $user,
                            $title,
                            $calendar->google_cal_id,
                            $start,
                            null, // end
                            null, // time
                            null, // time_end
                            $reminders,
                            $reminder_time,
                            $description,
                            'communication'
                        );
                        if ($events && isset($events[0]->id)) {
                            $input['calendar_id'] = $calendar->id;
                            $input['event_id'] = $events[0]->id;
                        }
                    }
                    if ($calendar->cal_type == 'outlook') {
                        $events = Outlook::CreateEvent(
                            $user,
                            $title,
                            $calendar->google_cal_id,
                            $start,
                            null, // end
                            null, // time
                            null, // time_end
                            $reminders,
                            $reminder_time,
                            $description,
                            'communication'
                        );
                        if ($events && isset($events[0]['id'])) {
                            $input['calendar_id'] = $calendar->id;
                            $input['event_id'] = $events[0]['id'];
                        }
                    }
                } catch (\Exception $calendarException) {
                    Log::error('Calendar integration failed:', ['error' => $calendarException->getMessage()]);
                    // Continue with task creation even if calendar fails
                }
            }

            if ($events && count($events) > 0) {
                return [
                    'calendar_id' => $calendar->id,
                    'event_id' => $events[0]->id,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to create calendar event for communication', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'team_id' => $team->id,
            ]);
        }

        return null;
    }

    /**
     * Handle calendar event updates
     */
    private static function handleCalendarEventUpdate(User $user, Team $team, Communication $communication, array &$validated): void
    {
        $calendar = $user->calendars()->where('team_id', $team->id)->first();
        if (! $calendar || ! $calendar->third_party_cal) {
            return;
        }

        $cal_type = $calendar->cal_type;
        $cal_id = $cal_type === 'google'
            ? $calendar->google_cal_id
            : $calendar->outlookIntegration?->external_id;
        if (! $cal_id) {
            return;
        }

        try {
            $hasNextAction = isset($validated['next_action_at']);
            $hasExistingEvent = ! empty($communication->event_id);

            // Determine if anything changed
            $eventDetailsChanged = $hasNextAction && (
                ($communication->next_action_at ?? null) !== ($validated['next_action_at'] ?? null) ||
                ($validated['subject'] ?? '') !== ($communication->subject ?? '') ||
                ($validated['notes'] ?? '') !== ($communication->notes ?? '') ||
                ($validated['next_action_type_id'] ?? '') !== ($communication->next_action_type_id ?? '') ||
                ($validated['communication_type_id'] ?? '') !== ($communication->communication_type_id ?? '')
            );

            if ($hasNextAction) {
                // Parse start datetime in user timezone
                $startDt = Carbon::parse($validated['next_action_at'])->setTimezone($user->timezone);
                $endDt = $startDt->copy()->addMinutes(30); // default 30 min

                $startDate = $startDt->format('Y-m-d');
                $endDate = $endDt->format('Y-m-d');
                $startTime = $startDt->format('H:i');
                $endTime = $endDt->format('H:i');

                // Build event title and description
                $typeId = $validated['communication_type_id'] ?? $communication->communication_type_id;
                $typeLabel = $typeId ? CommunicationType::fromId($typeId)?->label() : 'Communication';
                $title = $validated['subject'] ?? $communication->subject ?? "Follow-up: $typeLabel";

                $actionTypeId = $validated['next_action_type_id'] ?? $communication->next_action_type_id;
                $actionTypeLabel = NextActionType::fromId($actionTypeId)?->label() ?? 'Follow-up';

                $description = $actionTypeLabel;
                if (! empty($validated['notes'] ?? $communication->notes)) {
                    $description .= ': '.($validated['notes'] ?? $communication->notes);
                }

                $reminder = 2;
                $reminderTime = 30;
                $type = 'communication';

                // Update existing event
                if ($hasExistingEvent && $eventDetailsChanged) {
                    if ($cal_type === 'google') {
                        Googl::EditEvent(
                            $user,
                            $title,
                            $cal_id,
                            $communication->event_id,
                            $startDate,
                            $endDate,
                            $startTime,
                            $endTime,
                            $reminder,
                            $reminderTime,
                            $description,
                            $type
                        );
                    } elseif ($cal_type === 'outlook') {
                        Outlook::EditEvent(
                            $user,
                            $title,
                            $cal_id,
                            $communication->event_id,
                            $startDate,
                            $endDate,
                            $startTime,
                            $endTime,
                            $reminder,
                            $reminderTime,
                            $description,
                            $type
                        );
                    }
                }

                // Create new event
                elseif (! $hasExistingEvent) {
                    if ($cal_type === 'google') {
                        $events = Googl::CreateEvent(
                            $user,
                            $title,
                            $cal_id,
                            $startDate,
                            $endDate,
                            $startTime,
                            $endTime,
                            $reminder,
                            $reminderTime,
                            $description,
                            $type
                        );
                        if ($events && isset($events[0]->id)) {
                            $validated['calendar_id'] = $calendar->id;
                            $validated['event_id'] = $events[0]->id;
                        }
                    } elseif ($cal_type === 'outlook') {
                        $events = Outlook::CreateEvent(
                            $user,
                            $title,
                            $cal_id,
                            $startDate,
                            $endDate,
                            $startTime,
                            $endTime,
                            $reminder,
                            $reminderTime,
                            $description,
                            $type
                        );
                        if ($events && isset($events[0]['id'])) {
                            $validated['calendar_id'] = $calendar->id;
                            $validated['event_id'] = $events[0]['id'];
                        }
                    }
                }
            }

            // Delete existing event if next_action_at removed
            elseif ($hasExistingEvent && ! $hasNextAction) {
                if ($cal_type === 'google') {
                    Googl::RemoveEvent($user, $cal_id, $communication->event_id, 'communication');
                } elseif ($cal_type === 'outlook') {
                    Outlook::RemoveEvent($user, $cal_id, $communication->event_id, 'communication');
                }
                $validated['calendar_id'] = null;
                $validated['event_id'] = null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to update calendar event for communication', [
                'error' => $e->getMessage(),
                'communication_id' => $communication->id,
                'user_id' => $user->id,
                'team_id' => $team->id,
            ]);
        }
    }

    /**
     * Remove calendar event
     */
    private static function removeCalendarEvent(User $user, Team $team, string $eventId): void
    {
        $calendar = $user->calendars()->where('team_id', $team->id)->first();

        if (! $calendar || ! $calendar->third_party_cal || empty($eventId)) {
            return;
        }

        $cal_type = $calendar->cal_type;
        $cal_id = $cal_type === 'google' ? $calendar->google_cal_id : $calendar->outlookIntegration?->external_id;

        if (! $cal_id) {
            return;
        }

        try {
            if ($cal_type === 'google') {
                Googl::RemoveEvent($user, $cal_id, $eventId, 'communication');
            } elseif ($cal_type === 'outlook') {
                Outlook::RemoveEvent($user, $cal_id, $eventId, 'communication');
            }
        } catch (\Exception $e) {
            Log::error('Failed to remove calendar event for communication', [
                'error' => $e->getMessage(),
                'event_id' => $eventId,
                'user_id' => $user->id,
                'team_id' => $team->id,
            ]);
        }
    }

    /**
     * Get the related record (Lead, Contact, or Vendor)
     */
    private static function getRelatedRecord(string $type, int $id, Team $team)
    {
        // $modelClass = match ($type) {
        //     'lead', \App\Models\Lead::class => \App\Models\Lead::class,
        //     'contact', \App\Models\Contact::class => \App\Models\Contact::class,
        //     'vendor', \App\Models\Vendor::class => \App\Models\Vendor::class,
        // };

        // return $modelClass::where('team_id', $team->id)->findOrFail($id);
    }
}
