<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Fleet\Actions\CreateFleet;
use App\Domain\Fleet\Actions\DeleteFleet;
use App\Domain\Fleet\Actions\UpdateFleet;
use App\Domain\Fleet\Models\Fleet;
use App\Domain\FleetMaintenance\Models\FleetMaintenance;
use App\Domain\Location\Models\Location;
use App\Enums\Fleet\FleetStatus;
use App\Enums\Fleet\FleetType;
use App\Enums\Fleet\FuelType;
use App\Enums\Fleet\WeightUnit;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FleetController extends Controller
{
    public function __construct(
        private CreateFleet $createFleet,
        private UpdateFleet $updateFleet,
        private DeleteFleet $deleteFleet,
    ) {
        //
    }

    public function index(Request $request): Response
    {
        $tab = $request->string('tab', 'trucks')->toString() === 'trailers' ? 'trailers' : 'trucks';
        $truckPage = $tab === 'trucks' ? max(1, (int) $request->get('page', 1)) : 1;
        $trailerPage = $tab === 'trailers' ? max(1, (int) $request->get('page', 1)) : 1;

        $trucks = $this->buildFleetListQuery($request, FleetType::Truck)
            ->with(['location:id,display_name,city'])
            ->orderBy('display_name')
            ->paginate(25, ['*'], 'page', $truckPage)
            ->through(fn (Fleet $f) => $this->transformFleet($f, false))
            ->appends($this->indexQueryForPaginator($request, 'trucks'));

        $trailers = $this->buildFleetListQuery($request, FleetType::Trailer)
            ->with(['location:id,display_name,city'])
            ->orderBy('display_name')
            ->paginate(25, ['*'], 'page', $trailerPage)
            ->through(fn (Fleet $f) => $this->transformFleet($f, false))
            ->appends($this->indexQueryForPaginator($request, 'trailers'));

        $stats = $this->fleetStats();

        $locations = Location::query()
            ->orderBy('display_name')
            ->withCount([
                'fleets as truck_count' => fn ($q) => $q->where('type', FleetType::Truck->value),
                'fleets as trailer_count' => fn ($q) => $q->where('type', FleetType::Trailer->value),
                'fleets as available_count' => fn ($q) => $q->where('status', FleetStatus::Active->value),
            ])
            ->get(['id', 'display_name', 'city', 'address_line_1', 'state'])
            ->map(static function (Location $loc) {
                return [
                    'id' => $loc->id,
                    'display_name' => $loc->display_name,
                    'city' => $loc->city,
                    'address' => $loc->address_line_1,
                    'truck_count' => (int) $loc->truck_count,
                    'trailer_count' => (int) $loc->trailer_count,
                    'available_count' => (int) $loc->available_count,
                ];
            });

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'location_id' => $request->filled('location_id') ? $request->string('location_id')->toString() : null,
            'status' => $request->string('status')->toString() ?: null,
            'tab' => $tab,
        ];

        return Inertia::render('Tenant/Fleet/Index', [
            'trucks' => $trucks,
            'trailers' => $trailers,
            'stats' => $stats,
            'locations' => $locations,
            'filters' => $filters,
        ]);
    }

    public function createTruck(): Response
    {
        return $this->renderCreate(FleetType::Truck);
    }

    public function createTrailer(): Response
    {
        return $this->renderCreate(FleetType::Trailer);
    }

    private function renderCreate(FleetType $type): Response
    {
        $locations = Location::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'city', 'address_line_1']);

        $statuses = array_map(
            static fn (FleetStatus $s) => ['value' => $s->value, 'label' => str_replace('_', ' ', ucfirst($s->name))],
            FleetStatus::cases()
        );

        return Inertia::render('Tenant/Fleet/Create', [
            'fleetType' => $type->value,
            'locations' => $locations,
            'statuses' => $statuses,
            'fuelTypes' => FuelType::options(),
            'weightUnits' => WeightUnit::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $result = ($this->createFleet)($request->all());

        if (! $result['success']) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['fleet' => $result['message'] ?? 'Could not create fleet item.']);
        }

        $fleet = $result['record'];
        if (! $fleet instanceof Fleet) {
            return redirect()->back()->withInput();
        }

        return redirect()
            ->route('fleet.show', $fleet)
            ->with('success', 'Fleet item created.');
    }

    public function show(Fleet $fleet): Response
    {
        $fleet->load([
            'location:id,display_name,city,address_line_1,state',
            'maintenanceLogs' => static fn ($q) => $q
                ->with(['maintenanceTypes:id,display_name,category,applies_to'])
                ->orderByDesc('performed_at')
                ->orderByDesc('id'),
        ]);

        $statuses = array_map(
            static fn (FleetStatus $s) => ['value' => $s->value, 'label' => str_replace('_', ' ', ucfirst($s->name))],
            FleetStatus::cases()
        );

        return Inertia::render('Tenant/Fleet/Show', [
            'record' => $this->transformFleet($fleet, detailed: true),
            'statuses' => $statuses,
        ]);
    }

    public function edit(Fleet $fleet): Response|RedirectResponse
    {
        $locations = Location::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'city', 'address_line_1']);

        $statuses = array_map(
            static fn (FleetStatus $s) => ['value' => $s->value, 'label' => str_replace('_', ' ', ucfirst($s->name))],
            FleetStatus::cases()
        );

        $fleet->load('location:id,display_name,city,address_line_1,state');

        return Inertia::render('Tenant/Fleet/Edit', [
            'record' => $this->transformFleet($fleet, detailed: true),
            'locations' => $locations,
            'statuses' => $statuses,
            'fuelTypes' => FuelType::options(),
            'weightUnits' => WeightUnit::options(),
        ]);
    }

    public function update(Request $request, Fleet $fleet): RedirectResponse
    {
        $result = ($this->updateFleet)($fleet->id, $request->all());

        if (! $result['success']) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['fleet' => $result['message'] ?? 'Could not update fleet item.']);
        }

        $updated = $result['record'];
        if (! $updated instanceof Fleet) {
            return redirect()->back()->withInput();
        }

        return redirect()
            ->route('fleet.show', $updated)
            ->with('success', 'Fleet item updated.');
    }

    public function destroy(Fleet $fleet): RedirectResponse
    {
        $result = ($this->deleteFleet)($fleet->id);

        if (! $result['success']) {
            return redirect()
                ->route('fleet.index')
                ->withErrors(['fleet' => $result['message'] ?? 'Could not delete.']);
        }

        return redirect()
            ->route('fleet.index', ['tab' => $fleet->isTruck() ? 'trucks' : 'trailers'])
            ->with('success', 'Fleet item deleted.');
    }

    /**
     * @return array{total_trucks: int, total_trailers: int, available: int, in_use: int, maintenance: int, out_of_service: int}
     */
    private function fleetStats(): array
    {
        $base = Fleet::query();

        return [
            'total_trucks' => (clone $base)->trucks()->count(),
            'total_trailers' => (clone $base)->trailers()->count(),
            'available' => (clone $base)->where('status', FleetStatus::Active)->count(),
            'in_use' => 0,
            'maintenance' => (clone $base)->where('status', FleetStatus::Maintenance)->count(),
            'out_of_service' => (clone $base)->where('status', FleetStatus::Inactive)->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Fleet>
     */
    private function buildFleetListQuery(Request $request, FleetType $type)
    {
        $q = Fleet::query()->where('type', $type->value);

        if ($s = $request->string('search')->trim()->toString()) {
            $pattern = '%'.addcslashes($s, '%_\\').'%';
            $q->where(function ($w) use ($pattern) {
                $w->whereRaw('LOWER(display_name) LIKE LOWER(?)', [$pattern])
                    ->orWhereRaw('LOWER(license_plate) LIKE LOWER(?)', [$pattern])
                    ->orWhereRaw('LOWER(make) LIKE LOWER(?)', [$pattern])
                    ->orWhereRaw('LOWER(model) LIKE LOWER(?)', [$pattern])
                    ->orWhereRaw('LOWER(vin) LIKE LOWER(?)', [$pattern])
                    ->orWhereRaw('LOWER(size) LIKE LOWER(?)', [$pattern]);
            });
        }
        if ($request->filled('location_id')) {
            $q->where('location_id', $request->integer('location_id'));
        }
        if ($st = $request->string('status')->toString()) {
            $q->where('status', $st);
        }

        return $q;
    }

    /**
     * @return array<string, string|int|null|array<string, mixed>>
     */
    private function indexQueryForPaginator(Request $request, string $forTab): array
    {
        $out = [];
        if ($search = $request->string('search')->trim()->toString()) {
            $out['search'] = $search;
        }
        if ($request->filled('location_id')) {
            $out['location_id'] = (string) $request->input('location_id');
        }
        if ($st = $request->string('status')->toString()) {
            $out['status'] = $st;
        }
        $out['tab'] = $forTab;

        return $out;
    }

    private function transformFleet(Fleet $fleet, bool $detailed = false): array
    {
        $out = [
            'id' => $fleet->id,
            'display_name' => $fleet->display_name,
            'unit_number' => $fleet->license_plate ?: (string) $fleet->id,
            'trailer_type' => null,
            'type' => $fleet->type?->value,
            'license_plate' => $fleet->license_plate,
            'make' => $fleet->make,
            'model' => $fleet->model,
            'year' => $fleet->year,
            'size' => $fleet->size,
            'fuel_type' => $fleet->fuel_type?->value,
            'capacity' => $fleet->size,
            'status' => $fleet->status?->value,
            'vin' => $fleet->vin,
            'weight_capacity' => $fleet->weight_capacity,
            'weight_unit' => $fleet->weight_unit?->value,
            'towing_capacity' => $fleet->towing_capacity,
            'payload_capacity' => $fleet->payload_capacity,
            'gvwr' => $fleet->gvwr,
            'axle_count' => $fleet->axle_count,
            'specs' => $fleet->specs,
            'mileage' => $fleet->mileage,
            'hours' => $fleet->hours,
            'notes' => $fleet->notes,
            'last_maintenance_at' => $fleet->last_maintenance_at?->toDateString(),
            'next_maintenance_due_at' => $fleet->next_maintenance_due_at?->toDateString(),
            'maintenance_interval_days' => $fleet->maintenance_interval_days,
            'location_id' => $fleet->location_id,
            'location' => $fleet->location ? [
                'id' => $fleet->location->id,
                'display_name' => $fleet->location->display_name,
                'city' => $fleet->location->city,
                'address' => $fleet->location->address_line_1,
            ] : null,
        ];

        if (! $detailed) {
            return $out;
        }

        $out['driver'] = null;
        $out['truck'] = null;

        $out['maintenance_logs'] = [];
        if ($fleet->relationLoaded('maintenanceLogs')) {
            $out['maintenance_logs'] = $fleet->maintenanceLogs
                ->map(fn (FleetMaintenance $log) => $this->transformMaintenanceLog($log))
                ->values()
                ->all();
        }

        return $out;
    }

    /**
     * @return array<string, int|float|string|null>
     */
    private function transformMaintenanceLog(FleetMaintenance $log): array
    {
        $types = $log->relationLoaded('maintenanceTypes')
            ? $log->maintenanceTypes
            : collect();

        return [
            'id' => $log->id,
            'fleet_id' => $log->fleet_id,
            'performed_at' => $log->performed_at?->toDateString(),
            'type_ids' => $types->pluck('id')->values()->all(),
            'maintenance_types' => $types->map(static fn ($t) => [
                'id' => $t->id,
                'display_name' => $t->display_name,
                'category' => $t->category,
                'applies_to' => $t->applies_to?->value,
            ])->values()->all(),
            'cost' => $log->cost !== null ? (float) $log->cost : null,
            'mileage' => $log->mileage,
            'hours' => $log->hours,
            'notes' => $log->notes,
        ];
    }
}
