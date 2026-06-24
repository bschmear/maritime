<?php

declare(strict_types=1);

namespace App\Domain\BoatShow\Support;

use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Support\TenantAbsoluteUrl;
use Illuminate\Support\Collection;

final class BoatShowWordPressPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forShow(BoatShow $show): array
    {
        return [
            'uuid' => $show->uuid,
            'display_name' => $show->display_name,
            'slug' => $show->slug,
            'description' => $show->description,
            'website' => $show->website,
            'app_show_url' => TenantAbsoluteUrl::path('boat-shows/'.$show->slug),
            'updated_at' => $show->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forEvent(BoatShowEvent $event): array
    {
        $event->loadMissing('show');

        return [
            'uuid' => $event->uuid,
            'boat_show_uuid' => $event->show?->uuid,
            'display_name' => $event->display_name,
            'year' => $event->year,
            'starts_at' => $event->starts_at?->toDateString(),
            'ends_at' => $event->ends_at?->toDateString(),
            'venue' => $event->venue,
            'address_line_1' => $event->address_line_1,
            'address_line_2' => $event->address_line_2,
            'city' => $event->city,
            'state' => $event->state,
            'country' => $event->country,
            'postal_code' => $event->postal_code,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'booth' => $event->booth,
            'active' => (bool) $event->active,
            'app_event_url' => TenantAbsoluteUrl::path('boat-show-events/'.$event->id),
            'public_event_url' => TenantAbsoluteUrl::path('boat-show-events/'.$event->uuid.'/public'),
            'updated_at' => $event->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{shows: list<array<string, mixed>>, events: list<array<string, mixed>>}
     */
    public static function all(): array
    {
        $shows = BoatShow::query()
            ->orderBy('display_name')
            ->get();

        $events = BoatShowEvent::query()
            ->with('show')
            ->orderByDesc('year')
            ->orderBy('display_name')
            ->get();

        return [
            'shows' => $shows->map(fn (BoatShow $show) => self::forShow($show))->values()->all(),
            'events' => $events->map(fn (BoatShowEvent $event) => self::forEvent($event))->values()->all(),
        ];
    }

    /**
     * @return array{show: array<string, mixed>, events: list<array<string, mixed>>}
     */
    public static function forShowWithEvents(BoatShow $show): array
    {
        $show->loadMissing(['events' => fn ($query) => $query->orderByDesc('year')]);

        return [
            'show' => self::forShow($show),
            'events' => $show->events
                ->map(fn (BoatShowEvent $event) => self::forEvent($event))
                ->values()
                ->all(),
        ];
    }
}
