<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\BoatShow\Actions\CreateBoatShow as CreateAction;
use App\Domain\BoatShow\Actions\DeleteBoatShow as DeleteAction;
use App\Domain\BoatShow\Actions\UpdateBoatShow as UpdateAction;
use App\Domain\BoatShow\Models\BoatShow as RecordModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * @return array{upcomingEvents: \Illuminate\Support\Collection, pastEvents: \Illuminate\Support\Collection}
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
}
