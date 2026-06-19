<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\BoatShow\Actions\CreateBoatShow as CreateAction;
use App\Domain\BoatShow\Actions\DeleteBoatShow as DeleteAction;
use App\Domain\BoatShow\Actions\UpdateBoatShow as UpdateAction;
use App\Domain\BoatShow\Models\BoatShow as RecordModel;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Response as InertiaResponse;

class BoatShowController extends RecordController
{
    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'boat-shows',
            'BoatShow',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'BoatShow'
        );
    }

    public function show(Request $request, $id)
    {
        $response = parent::show($request, $id);
        $eventsBundle = $this->partitionedEventsForBoatShow($id);

        if ($response instanceof InertiaResponse) {
            return $response->with($eventsBundle);
        }

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (! is_array($data)) {
                $data = json_decode(json_encode($data), true) ?? [];
            }

            return response()->json(array_merge($data, $eventsBundle));
        }

        return $response;
    }

    /**
     * @return array{upcomingEvents: Collection, pastEvents: Collection}
     */
    protected function partitionedEventsForBoatShow(string|int $boatShowId): array
    {
        $events = RecordModel::query()
            ->findOrFail($boatShowId)
            ->events()
            ->orderByDesc('year')
            ->orderByDesc('starts_at')
            ->get();

        $today = now()->startOfDay();
        $upcoming = collect();
        $past = collect();

        foreach ($events as $event) {
            $isPast = false;
            if ($event->ends_at) {
                $isPast = $event->ends_at->lt($today);
            } elseif ($event->starts_at) {
                $isPast = $event->starts_at->lt($today);
            }

            if ($isPast) {
                $past->push($event);
            } else {
                $upcoming->push($event);
            }
        }

        $upcoming = $upcoming->sortBy(function ($e) {
            return $e->starts_at?->startOfDay()->timestamp ?? PHP_INT_MAX;
        })->values();

        $past = $past->sortByDesc(function ($e) {
            return ($e->ends_at ?? $e->starts_at)?->timestamp ?? 0;
        })->values();

        return [
            'upcomingEvents' => $upcoming,
            'pastEvents' => $past,
        ];
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $month = $request->query('month');
        if (! is_string($month) || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json(['message' => 'A valid month (YYYY-MM) is required.'], 422);
        }

        [$year, $monthNum] = array_map('intval', explode('-', $month));
        $monthStart = Carbon::create($year, $monthNum, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $today = now()->startOfDay();

        $events = BoatShowEvent::query()
            ->with(['show:id,display_name,slug'])
            ->whereNotNull('starts_at')
            ->where(function ($query) use ($monthStart, $monthEnd) {
                $query->whereDate('starts_at', '<=', $monthEnd)
                    ->where(function ($inner) use ($monthStart) {
                        $inner->whereDate('ends_at', '>=', $monthStart)
                            ->orWhere(function ($open) use ($monthStart) {
                                $open->whereNull('ends_at')
                                    ->whereDate('starts_at', '>=', $monthStart);
                            });
                    });
            })
            ->where(function ($query) use ($today) {
                $query->where(function ($inner) use ($today) {
                    $inner->whereNotNull('ends_at')
                        ->whereDate('ends_at', '>=', $today);
                })->orWhere(function ($inner) use ($today) {
                    $inner->whereNull('ends_at')
                        ->whereDate('starts_at', '>=', $today);
                });
            })
            ->orderBy('starts_at')
            ->orderBy('display_name')
            ->get()
            ->map(function (BoatShowEvent $event) {
                $show = $event->show;
                $location = collect([
                    $event->venue,
                    trim(implode(', ', array_filter([$event->city, $event->state]))),
                    $event->country,
                ])->filter()->implode(' · ');

                return [
                    'id' => $event->id,
                    'name' => $event->display_name,
                    'start_date' => $event->starts_at?->format('Y-m-d'),
                    'end_date' => $event->ends_at?->format('Y-m-d') ?? $event->starts_at?->format('Y-m-d'),
                    'location' => $location !== '' ? $location : null,
                    'description' => $show?->display_name,
                    'boat_show_name' => $show?->display_name,
                    'url' => $show
                        ? route('boat-shows.events.show', [
                            'boatShow' => $show->getRouteKey(),
                            'event' => $event->getRouteKey(),
                        ])
                        : null,
                ];
            })
            ->values();

        return response()->json(['events' => $events]);
    }
}
