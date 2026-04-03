<?php

declare(strict_types=1);

namespace App\Domain\BoatShowEvent\Support;

use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\Lead\Models\Lead;
use App\Jobs\SendBoatShowEventFollowUpJob;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

final class BoatShowFollowUpScheduler
{
    public static function scheduleIfApplicable(BoatShowEvent $event, Lead $lead): void
    {
        $event->refresh();
        $lead->refresh();

        if (! $event->auto_followup) {
            return;
        }

        if (blank($lead->email)) {
            return;
        }

        $delay = self::delayUntil($event);

        try {
            SendBoatShowEventFollowUpJob::dispatch($event->id, $lead->id)->delay($delay);
        } catch (\Throwable $e) {
            Log::error('Failed to queue boat show follow-up email', [
                'event_id' => $event->id,
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function delayUntil(BoatShowEvent $event): CarbonInterface
    {
        $amount = max(0, (int) ($event->delay_amount ?? 1));
        $unit = $event->delay_unit ?? 'days';

        return match ($unit) {
            'minutes' => now()->addMinutes($amount),
            'hours' => now()->addHours($amount),
            'days' => now()->addDays($amount),
            default => now()->addDays($amount),
        };
    }
}
