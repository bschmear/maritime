<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Fleet\Models\Fleet;
use App\Domain\FleetMaintenance\Actions\CreateFleetMaintenance;
use App\Domain\FleetMaintenance\Actions\DeleteFleetMaintenance;
use App\Domain\FleetMaintenance\Actions\UpdateFleetMaintenance;
use App\Domain\FleetMaintenance\Models\FleetMaintenance;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FleetMaintenanceController extends Controller
{
    public function __construct(
        private CreateFleetMaintenance $createFleetMaintenance,
        private UpdateFleetMaintenance $updateFleetMaintenance,
        private DeleteFleetMaintenance $deleteFleetMaintenance,
    ) {
        //
    }

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $pattern = $search !== '' ? '%'.addcslashes($search, '%_\\').'%' : null;

        $filteredFleet = null;
        if ($request->filled('fleet_id')) {
            $fleetId = $request->integer('fleet_id');
            $filteredFleet = Fleet::query()
                ->select(['id', 'display_name', 'license_plate', 'type'])
                ->find($fleetId);
        }

        $query = FleetMaintenance::query()
            ->with([
                'fleet:id,display_name,license_plate,type',
                'maintenanceTypes:id,display_name,category,applies_to',
            ])
            ->when($filteredFleet, fn ($q) => $q->where('fleet_id', $filteredFleet->id))
            ->orderByDesc('performed_at')
            ->orderByDesc('id');

        if ($pattern !== null) {
            $query->where(function ($q) use ($pattern, $filteredFleet) {
                $q->whereRaw('LOWER(notes) LIKE LOWER(?)', [$pattern])
                    ->orWhereRaw('CAST(fleet_maintenance_logs.id AS TEXT) LIKE LOWER(?)', [$pattern])
                    ->orWhereHas('maintenanceTypes', function ($tq) use ($pattern) {
                        $tq->whereRaw('LOWER(display_name) LIKE LOWER(?)', [$pattern])
                            ->orWhereRaw('LOWER(COALESCE(category, \'\')) LIKE LOWER(?)', [$pattern]);
                    });
                if ($filteredFleet === null) {
                    $q->orWhereHas('fleet', function ($fq) use ($pattern) {
                        $fq->whereRaw('LOWER(display_name) LIKE LOWER(?)', [$pattern])
                            ->orWhereRaw('LOWER(license_plate) LIKE LOWER(?)', [$pattern]);
                    });
                }
            });
        }

        $append = array_filter([
            'search' => $search !== '' ? $search : null,
            'fleet_id' => $filteredFleet?->id,
        ], fn ($v) => $v !== null && $v !== '');

        $logs = $query->paginate(20)->appends($append);

        return Inertia::render('Tenant/FleetMaintenance/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'fleet_id' => $filteredFleet?->id,
            ],
            'filteredFleet' => $filteredFleet ? [
                'id' => $filteredFleet->id,
                'display_name' => $filteredFleet->display_name,
                'license_plate' => $filteredFleet->license_plate,
                'type' => $filteredFleet->type?->value,
            ] : null,
        ]);
    }

    public function show(FleetMaintenance $maintenanceLog): Response
    {
        $maintenanceLog->load([
            'fleet:id,display_name,license_plate,type,make,model',
            'maintenanceTypes:id,display_name,category,applies_to',
        ]);

        return Inertia::render('Tenant/FleetMaintenance/Show', [
            'log' => $this->transformLog($maintenanceLog),
        ]);
    }

    public function store(Request $request, Fleet $fleet): RedirectResponse
    {
        $data = array_merge($request->all(), ['fleet_id' => $fleet->id]);
        $result = ($this->createFleetMaintenance)($data);

        if (! $result['success']) {
            return back()
                ->withInput()
                ->withErrors(['maintenance' => $result['message'] ?? 'Could not save maintenance record.']);
        }

        $this->syncFleetMaintenanceSchedule($fleet->id);

        return redirect()
            ->route('fleet.show', $fleet)
            ->with('success', 'Maintenance record added.');
    }

    public function update(Request $request, Fleet $fleet, FleetMaintenance $maintenanceLog): RedirectResponse
    {
        $this->abortUnlessFleetOwnsLog($fleet, $maintenanceLog);

        $result = ($this->updateFleetMaintenance)($maintenanceLog->id, $request->all());

        if (! $result['success']) {
            return back()
                ->withInput()
                ->withErrors(['maintenance' => $result['message'] ?? 'Could not update maintenance record.']);
        }

        $this->syncFleetMaintenanceSchedule($fleet->id);

        return back()->with('success', 'Maintenance record updated.');
    }

    public function destroy(Fleet $fleet, FleetMaintenance $maintenanceLog): RedirectResponse
    {
        $this->abortUnlessFleetOwnsLog($fleet, $maintenanceLog);

        $result = ($this->deleteFleetMaintenance)($maintenanceLog->id);

        if (! $result['success']) {
            return back()->withErrors(['maintenance' => $result['message'] ?? 'Could not delete record.']);
        }

        $this->syncFleetMaintenanceSchedule($fleet->id);

        return back()->with('success', 'Maintenance record deleted.');
    }

    private function abortUnlessFleetOwnsLog(Fleet $fleet, FleetMaintenance $maintenanceLog): void
    {
        if ((int) $maintenanceLog->fleet_id !== (int) $fleet->id) {
            abort(404);
        }
    }

    /**
     * Set fleet last_maintenance_at from the latest log, and next_maintenance_due_at when an interval (days) is configured.
     */
    private function syncFleetMaintenanceSchedule(int $fleetId): void
    {
        $fleet = Fleet::query()->find($fleetId);
        if (! $fleet) {
            return;
        }

        $latest = FleetMaintenance::query()->where('fleet_id', $fleetId)->max('performed_at');
        $last = $latest ? Carbon::parse((string) $latest)->startOfDay() : null;
        $fleet->last_maintenance_at = $last;

        if ($last === null) {
            $fleet->next_maintenance_due_at = null;
        } else {
            $intervalDays = $fleet->maintenance_interval_days;
            if ($intervalDays !== null && (int) $intervalDays > 0) {
                $fleet->next_maintenance_due_at = $last->copy()->addDays((int) $intervalDays);
            }
        }

        $fleet->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function transformLog(FleetMaintenance $log): array
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
            'created_at' => $log->created_at?->toIso8601String(),
            'fleet' => $log->fleet ? [
                'id' => $log->fleet->id,
                'display_name' => $log->fleet->display_name,
                'license_plate' => $log->fleet->license_plate,
                'type' => $log->fleet->type?->value,
                'make' => $log->fleet->make,
                'model' => $log->fleet->model,
            ] : null,
        ];
    }
}
