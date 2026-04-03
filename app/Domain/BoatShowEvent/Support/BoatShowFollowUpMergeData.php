<?php

declare(strict_types=1);

namespace App\Domain\BoatShowEvent\Support;

use App\Domain\Asset\Models\Asset;
use App\Domain\BoatShow\Models\BoatShowLead;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\Lead\Models\Lead;
use App\Domain\User\Models\User;
use App\Models\Account;
use App\Models\AccountSettings;

/**
 * Builds {{ token }} replacement map for {@see BoatShowFollowUpMerger}.
 *
 * @return array<string, string> keys are full tokens e.g. '{{ lead_name }}'
 */
final class BoatShowFollowUpMergeData
{
    /**
     * @param  list<int>  $assetIds
     * @return array<string, string>
     */
    public static function forLeadAndEvent(
        Lead $lead,
        BoatShowEvent $event,
        Account $centralAccount,
        User $salesperson,
        array $assetIds,
    ): array {
        $event->loadMissing('show:id,display_name');

        $leadName = trim((string) ($lead->display_name ?? ''));
        if ($leadName === '') {
            $leadName = trim(implode(' ', array_filter([$lead->first_name, $lead->last_name])));
        }
        if ($leadName === '') {
            $leadName = 'there';
        }

        $venue = trim((string) ($event->venue ?? ''));
        if ($venue === '') {
            $venue = trim(implode(', ', array_filter([$event->city, $event->state])));
        }
        if ($venue === '') {
            $venue = 'our event';
        }

        $salespersonLabel = trim((string) ($salesperson->display_name ?? ''));
        if ($salespersonLabel === '') {
            $salespersonLabel = trim(implode(' ', array_filter([$salesperson->first_name, $salesperson->last_name])));
        }
        if ($salespersonLabel === '') {
            $salespersonLabel = (string) $salesperson->email;
        }

        $settings = AccountSettings::getCurrent();
        $dealerName = (string) ($centralAccount->name ?? $settings->settings['company_name'] ?? config('app.name'));

        return [
            '{{ lead_name }}' => e($leadName),
            '{{ lead_email }}' => e((string) ($lead->email ?? '')),
            '{{ event_name }}' => e((string) ($event->display_name ?? 'Boat show')),
            '{{ event_venue }}' => e($venue),
            '{{ boat_show_name }}' => e((string) ($event->show?->display_name ?? '')),
            '{{ dealer_name }}' => e($dealerName),
            '{{ salesperson_name }}' => e($salespersonLabel),
            '{{ today }}' => e(now()->timezone($settings->timezone ?? config('app.timezone'))->format('F j, Y')),
            '{{ selected_asset_list }}' => self::selectedAssetListHtml($assetIds),
        ];
    }

    /**
     * @return list<int>
     */
    public static function assetIdsForSubmission(BoatShowEvent $event, Lead $lead): array
    {
        $row = BoatShowLead::query()
            ->where('boat_show_event_id', $event->id)
            ->where('leadable_type', $lead->getMorphClass())
            ->where('leadable_id', $lead->getKey())
            ->orderByDesc('id')
            ->first();

        $ids = $row?->meta['asset_ids'] ?? [];

        return array_values(array_unique(array_map('intval', is_array($ids) ? $ids : [])));
    }

    /**
     * @param  list<int>  $assetIds
     */
    private static function selectedAssetListHtml(array $assetIds): string
    {
        if ($assetIds === []) {
            return '<p><em>No specific units were selected.</em></p>';
        }

        $names = Asset::query()
            ->whereIn('id', $assetIds)
            ->orderBy('id')
            ->pluck('display_name');

        if ($names->isEmpty()) {
            return '<p><em>No specific units were selected.</em></p>';
        }

        $items = $names->map(fn ($name) => '<li>'.e((string) $name).'</li>')->implode('');

        return '<ul>'.$items.'</ul>';
    }
}
