<?php

namespace App\Services;

use App\Domain\Communication\Models\Communication;
use App\Domain\User\Models\User;
use App\Enums\Communication\CommunicationType;
use App\Enums\Communication\NextActionType;
use App\Enums\Communication\Outcome;
use App\Enums\Communication\Priority;
use App\Enums\Communication\Status;
use Carbon\Carbon;

class CommunicationStatsService
{
    public function getActionItems(User $user, bool $isAdmin = false, bool $viewingTeam = false): array
    {
        $baseQuery = Communication::query();

        if (! $viewingTeam) {
            $baseQuery->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        return [
            'overdue_follow_ups' => $this->getOverdueFollowUps($baseQuery),
            'waiting_responses' => $this->getWaitingResponses($baseQuery),
            'high_priority_open' => $this->getHighPriorityOpen($baseQuery),
            'todays_scheduled' => $this->getTodaysScheduled($baseQuery),
            'needs_attention_count' => $this->getNeedsAttentionCount($baseQuery),
        ];
    }

    /**
     * Get today's activity summary for dashboard cards
     */
    public function getTodaysSummary(User $user, bool $isAdmin = false, bool $viewingTeam = false): array
    {
        $baseQuery = Communication::query()
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]);

        if (! $viewingTeam) {
            $baseQuery->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        $todayComms = $baseQuery->get();
        $callTypeId = CommunicationType::Call->id();
        $emailTypeId = CommunicationType::Email->id();

        return [
            'total_today' => $todayComms->count(),
            'calls_today' => $todayComms->where('communication_type_id', $callTypeId)->count(),
            'emails_today' => $todayComms->where('communication_type_id', $emailTypeId)->count(),
            'inbound_today' => $todayComms->where('direction', 'inbound')->count(),
            'outbound_today' => $todayComms->where('direction', 'outbound')->count(),
        ];
    }

    /**
     * Get this week's summary for dashboard cards
     */
    public function getWeeksSummary(User $user, bool $isAdmin = false, bool $viewingTeam = false): array
    {
        $baseQuery = Communication::query()
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);

        if (! $viewingTeam) {
            $baseQuery->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        $weekComms = $baseQuery->get();
        $callTypeId = CommunicationType::Call->id();
        $emailTypeId = CommunicationType::Email->id();
        $meetingTypeId = CommunicationType::Meeting->id();

        return [
            'total_week' => $weekComms->count(),
            'calls_week' => $weekComms->where('communication_type_id', $callTypeId)->count(),
            'emails_week' => $weekComms->where('communication_type_id', $emailTypeId)->count(),
            'meetings_week' => $weekComms->where('communication_type_id', $meetingTypeId)->count(),
        ];
    }

    /**
     * Get comprehensive dashboard data
     */
    public function getDashboardData(User $user, bool $isAdmin = false, bool $viewingTeam = false): array
    {
        return [
            'action_items' => $this->getActionItems($user, $isAdmin, $viewingTeam),
            'today_summary' => $this->getTodaysSummary($user, $isAdmin, $viewingTeam),
            'week_summary' => $this->getWeeksSummary($user, $isAdmin, $viewingTeam),
            'waiting_responses_count' => $this->getWaitingResponsesCount($user, $isAdmin, $viewingTeam),
        ];
    }

    // Private methods for action items

    private function getOverdueFollowUps($baseQuery): array
    {
        $overdueComms = $baseQuery->clone()
            ->where('next_action_at', '<', now())
            ->whereNotNull('next_action_at')
            ->whereIn('status_id', [Status::Open->id(), Status::Waiting->id()])
            ->with('communicable')
            ->orderBy('next_action_at', 'asc')
            ->limit(5)
            ->get();

        return [
            'count' => $overdueComms->count(),
            'items' => $overdueComms->map(function ($comm) {
                return [
                    'id' => $comm->id,
                    'subject' => $comm->subject,
                    'next_action_at' => $comm->next_action_at->format('M j, Y g:i A'),
                    'next_action_type_id' => $comm->next_action_type_id,
                    'communicable_name' => $this->getCommunicableName($comm),
                    'days_overdue' => now()->diffInDays($comm->next_action_at),
                ];
            })->toArray(),
        ];
    }

    private function getWaitingResponses($baseQuery): array
    {
        $waitingComms = $baseQuery->clone()
            ->where('status_id', Status::Waiting->id())
            ->where('created_at', '<', now()->subDays(3))
            ->with('communicable')
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get();

        return [
            'count' => $waitingComms->count(),
            'items' => $waitingComms->map(function ($comm) {
                return [
                    'id' => $comm->id,
                    'subject' => $comm->subject,
                    'created_at' => $comm->created_at->format('M j, Y'),
                    'communicable_name' => $this->getCommunicableName($comm),
                    'days_waiting' => now()->diffInDays($comm->created_at),
                ];
            })->toArray(),
        ];
    }

    private function getHighPriorityOpen($baseQuery): array
    {
        $highPriorityComms = $baseQuery->clone()
            ->where('priority_id', Priority::High->id())
            ->where('status_id', Status::Open->id())
            ->with('communicable')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'count' => $highPriorityComms->count(),
            'items' => $highPriorityComms->map(function ($comm) {
                return [
                    'id' => $comm->id,
                    'subject' => $comm->subject,
                    'created_at' => $comm->created_at->format('M j, Y'),
                    'communicable_name' => $this->getCommunicableName($comm),
                    'communication_type_id' => $comm->communication_type_id,
                ];
            })->toArray(),
        ];
    }

    private function getTodaysScheduled($baseQuery): array
    {
        $scheduledComms = $baseQuery->clone()
            ->whereBetween('next_action_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereIn('status_id', [Status::Open->id(), Status::Waiting->id()])
            ->with('communicable')
            ->orderBy('next_action_at', 'asc')
            ->get();

        return [
            'count' => $scheduledComms->count(),
            'items' => $scheduledComms->map(function ($comm) {
                return [
                    'id' => $comm->id,
                    'subject' => $comm->subject,
                    'next_action_at' => $comm->next_action_at->format('g:i A'),
                    'next_action_type_id' => $comm->next_action_type_id,
                    'communicable_name' => $this->getCommunicableName($comm),
                ];
            })->toArray(),
        ];
    }

    private function getNeedsAttentionCount($baseQuery): int
    {
        $threeDaysAgo = now()->subDays(3);

        return $baseQuery->clone()
            ->where(function ($query) use ($threeDaysAgo) {
                $query->where(function ($q) {
                    $q->where('next_action_at', '<', now())
                        ->whereNotNull('next_action_at')
                        ->whereIn('status_id', [Status::Open->id(), Status::Waiting->id()]);
                })
                    ->orWhere(function ($q) use ($threeDaysAgo) {
                        $q->where('status_id', Status::Waiting->id())
                            ->where('created_at', '<', $threeDaysAgo);
                    })
                    ->orWhere(function ($q) {
                        $q->where('priority_id', Priority::High->id())
                            ->where('status_id', Status::Open->id());
                    });
            })
            ->count();
    }

    private function getWaitingResponsesCount(User $user, bool $isAdmin = false, bool $viewingTeam = false): int
    {
        $baseQuery = Communication::where('status_id', Status::Waiting->id());

        if (! $viewingTeam) {
            $baseQuery->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        return $baseQuery->count();
    }

    private function getCommunicableName($communication): string
    {
        if (! $communication->communicable) {
            return 'No Related Record';
        }

        $type = $communication->communicable_type;

        if ($type === \App\Domain\Lead\Models\Lead::class) {
            return $communication->communicable->display_name
                ?? trim(implode(' ', array_filter([
                    $communication->communicable->first_name ?? '',
                    $communication->communicable->last_name ?? '',
                ])))
                ?: 'Lead';
        }

        if ($type === \App\Domain\Customer\Models\Customer::class) {
            return $communication->communicable->display_name ?? 'Customer';
        }

        if ($type === \App\Domain\Vendor\Models\Vendor::class) {
            return $communication->communicable->display_name
                ?? $communication->communicable->name
                ?? 'Vendor';
        }

        return 'Unknown Type';
    }

    /**
     * Get communication statistics over a time period
     */
    public function getTeamCommunicationsStats(
        User $user,
        bool $isAdmin = false,
        string $period = 'week',
        ?string $customFrom = null,
        ?string $customTo = null,
        bool $viewingTeam = false
    ): array {
        $dateRange = $this->getDateRange($period, $customFrom, $customTo);

        $communicationsQuery = Communication::query()
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);

        if (! $viewingTeam) {
            $communicationsQuery->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        $communications = $communicationsQuery->get();
        $useWeeklyBreakdown = $this->shouldUseWeeklyBreakdown($period, $dateRange);

        return [
            'period' => $period,
            'date_range' => [
                'from' => $dateRange['from']->toISOString(),
                'to' => $dateRange['to']->toISOString(),
            ],
            'totals' => $this->calculateTotals($communications),
            'breakdown' => $this->calculateBreakdown($communications),
            'by_record_type' => $this->calculateByRecordType($communications),
            'daily_breakdown' => $useWeeklyBreakdown
                ? $this->calculateWeeklyBreakdown($communications, $dateRange)
                : $this->calculateDailyBreakdown($communications, $dateRange),
            'chart_type' => $useWeeklyBreakdown ? 'weekly' : 'daily',
        ];
    }

    private function shouldUseWeeklyBreakdown(string $period, array $dateRange): bool
    {
        if ($period === 'week') {
            return false;
        }

        if ($period === 'custom') {
            return $dateRange['from']->diffInDays($dateRange['to']) > 7;
        }

        return true;
    }

    private function getDateRange(string $period, ?string $customFrom = null, ?string $customTo = null): array
    {
        if ($period === 'custom' && $customFrom && $customTo) {
            return [
                'from' => Carbon::parse($customFrom)->startOfDay(),
                'to' => Carbon::parse($customTo)->endOfDay(),
            ];
        }

        $from = match ($period) {
            'week' => now()->subDays(6)->startOfDay(),
            'month' => now()->subDays(29)->startOfDay(),
            'quarter' => now()->subDays(89)->startOfDay(),
            default => now()->subDays(6)->startOfDay(),
        };

        return ['from' => $from, 'to' => now()->endOfDay()];
    }

    private function calculateTotals($communications): array
    {
        return [
            'total_created' => $communications->count(),
            'total_inbound' => $communications->where('direction', 'inbound')->count(),
            'total_outbound' => $communications->where('direction', 'outbound')->count(),
        ];
    }

    private function calculateBreakdown($communications): array
    {
        return [
            'status' => [
                'open' => $communications->where('status_id', Status::Open->id())->count(),
                'waiting' => $communications->where('status_id', Status::Waiting->id())->count(),
                'closed' => $communications->where('status_id', Status::Closed->id())->count(),
            ],
            'priority' => [
                'low' => $communications->where('priority_id', Priority::Low->id())->count(),
                'medium' => $communications->where('priority_id', Priority::Medium->id())->count(),
                'high' => $communications->where('priority_id', Priority::High->id())->count(),
            ],
            'outcome' => [
                'connected' => $communications->where('outcome_id', Outcome::Connected->id())->count(),
                'no_answer' => $communications->where('outcome_id', Outcome::NoAnswer->id())->count(),
                'left_voicemail' => $communications->where('outcome_id', Outcome::LeftVoicemail->id())->count(),
                'not_interested' => $communications->where('outcome_id', Outcome::NotInterested->id())->count(),
                'other' => $communications->where('outcome_id', Outcome::Other->id())->count(),
            ],
            'next_action_type' => [
                'none' => $communications->where('next_action_type_id', NextActionType::None->id())->count(),
                'follow_up' => $communications->where('next_action_type_id', NextActionType::FollowUp->id())->count(),
                'meeting' => $communications->where('next_action_type_id', NextActionType::Meeting->id())->count(),
            ],
            'type' => [
                'call' => $communications->where('communication_type_id', CommunicationType::Call->id())->count(),
                'email' => $communications->where('communication_type_id', CommunicationType::Email->id())->count(),
                'text' => $communications->where('communication_type_id', CommunicationType::Text->id())->count(),
                'meeting' => $communications->where('communication_type_id', CommunicationType::Meeting->id())->count(),
            ],
        ];
    }

    private function calculateByRecordType($communications): array
    {
        return [
            'leads' => $communications->where('communicable_type', \App\Domain\Lead\Models\Lead::class)->count(),
            'customers' => $communications->where('communicable_type', \App\Domain\Customer\Models\Customer::class)->count(),
            'vendors' => $communications->where('communicable_type', \App\Domain\Vendor\Models\Vendor::class)->count(),
        ];
    }

    private function calculateDailyBreakdown($communications, array $dateRange): array
    {
        $dailyData = [];
        $currentDate = $dateRange['from']->copy();

        while ($currentDate <= $dateRange['to']) {
            $dateKey = $currentDate->format('Y-m-d');
            $dailyData[$dateKey] = [
                'date' => $dateKey,
                'date_formatted' => $currentDate->format('M j'),
                'total' => 0,
                'inbound' => 0,
                'outbound' => 0,
                'by_status' => ['open' => 0, 'waiting' => 0, 'closed' => 0],
                'by_priority' => ['low' => 0, 'medium' => 0, 'high' => 0],
                'by_type' => ['call' => 0, 'email' => 0, 'text' => 0, 'meeting' => 0],
            ];
            $currentDate->addDay();
        }

        $communicationsByDate = $communications->groupBy(function ($communication) {
            return Carbon::parse($communication->created_at)->format('Y-m-d');
        });

        foreach ($communicationsByDate as $date => $dayComms) {
            if (! isset($dailyData[$date])) {
                continue;
            }

            $dailyData[$date]['total'] = $dayComms->count();
            $dailyData[$date]['inbound'] = $dayComms->where('direction', 'inbound')->count();
            $dailyData[$date]['outbound'] = $dayComms->where('direction', 'outbound')->count();

            $dailyData[$date]['by_status'] = [
                'open' => $dayComms->where('status_id', Status::Open->id())->count(),
                'waiting' => $dayComms->where('status_id', Status::Waiting->id())->count(),
                'closed' => $dayComms->where('status_id', Status::Closed->id())->count(),
            ];

            $dailyData[$date]['by_priority'] = [
                'low' => $dayComms->where('priority_id', Priority::Low->id())->count(),
                'medium' => $dayComms->where('priority_id', Priority::Medium->id())->count(),
                'high' => $dayComms->where('priority_id', Priority::High->id())->count(),
            ];

            $dailyData[$date]['by_type'] = [
                'call' => $dayComms->where('communication_type_id', CommunicationType::Call->id())->count(),
                'email' => $dayComms->where('communication_type_id', CommunicationType::Email->id())->count(),
                'text' => $dayComms->where('communication_type_id', CommunicationType::Text->id())->count(),
                'meeting' => $dayComms->where('communication_type_id', CommunicationType::Meeting->id())->count(),
            ];
        }

        return array_values($dailyData);
    }

    private function calculateWeeklyBreakdown($communications, array $dateRange): array
    {
        $weeklyData = [];
        $currentDate = $dateRange['from']->copy()->startOfWeek();

        while ($currentDate <= $dateRange['to']) {
            $weekStart = $currentDate->copy();
            $weekEnd = $currentDate->copy()->endOfWeek();

            if ($weekEnd > $dateRange['to']) {
                $weekEnd = $dateRange['to']->copy();
            }

            $weekKey = $weekStart->format('Y-m-d');
            $weeklyData[$weekKey] = [
                'date' => $weekKey,
                'date_formatted' => $weekStart->format('M j').'-'.$weekEnd->format('j'),
                'week_start' => $weekStart->toISOString(),
                'week_end' => $weekEnd->toISOString(),
                'total' => 0,
                'inbound' => 0,
                'outbound' => 0,
                'by_status' => ['open' => 0, 'waiting' => 0, 'closed' => 0],
                'by_priority' => ['low' => 0, 'medium' => 0, 'high' => 0],
                'by_type' => ['call' => 0, 'email' => 0, 'text' => 0, 'meeting' => 0],
            ];
            $currentDate->addWeek();
        }

        foreach ($communications as $communication) {
            $weekKey = Carbon::parse($communication->created_at)->startOfWeek()->format('Y-m-d');

            if (! isset($weeklyData[$weekKey])) {
                continue;
            }

            $weeklyData[$weekKey]['total']++;

            $communication->direction === 'inbound'
                ? $weeklyData[$weekKey]['inbound']++
                : $weeklyData[$weekKey]['outbound']++;

            match (true) {
                $communication->status_id == Status::Open->id() => $weeklyData[$weekKey]['by_status']['open']++,
                $communication->status_id == Status::Waiting->id() => $weeklyData[$weekKey]['by_status']['waiting']++,
                $communication->status_id == Status::Closed->id() => $weeklyData[$weekKey]['by_status']['closed']++,
                default => null,
            };

            match (true) {
                $communication->priority_id == Priority::Low->id() => $weeklyData[$weekKey]['by_priority']['low']++,
                $communication->priority_id == Priority::Medium->id() => $weeklyData[$weekKey]['by_priority']['medium']++,
                $communication->priority_id == Priority::High->id() => $weeklyData[$weekKey]['by_priority']['high']++,
                default => null,
            };

            match (true) {
                $communication->communication_type_id == CommunicationType::Call->id() => $weeklyData[$weekKey]['by_type']['call']++,
                $communication->communication_type_id == CommunicationType::Email->id() => $weeklyData[$weekKey]['by_type']['email']++,
                $communication->communication_type_id == CommunicationType::Text->id() => $weeklyData[$weekKey]['by_type']['text']++,
                $communication->communication_type_id == CommunicationType::Meeting->id() => $weeklyData[$weekKey]['by_type']['meeting']++,
                default => null,
            };
        }

        return array_values($weeklyData);
    }
}
