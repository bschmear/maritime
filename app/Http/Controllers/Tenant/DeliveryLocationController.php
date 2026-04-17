<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\DeliveryLocation\Actions\CreateDeliveryLocation;
use App\Domain\DeliveryLocation\Actions\DeleteDeliveryLocation;
use App\Domain\DeliveryLocation\Actions\UpdateDeliveryLocation;
use App\Domain\DeliveryLocation\Models\DeliveryLocation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;

class DeliveryLocationController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = DeliveryLocation::query()
            ->when($request->search, function ($q, $search) {
                $like = '%'.strtolower($search).'%';
                $q->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(city, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(state, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(contact_name, \'\')) LIKE ?', [$like]);
                });
            })
            ->when($request->status === 'active', fn ($q) => $q->where('active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('active', false))
            ->orderBy('name');

        return Inertia::render('Tenant/DeliveryLocation/Index', [
            'deliveryLocations' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Tenant/DeliveryLocation/Create');
    }

    public function store(Request $request)
    {
        $result = (new CreateDeliveryLocation)($request->all());

        if (! ($result['success'] ?? true)) {
            return back()->withErrors(['general' => $result['message'] ?? 'Failed to save.'])->withInput();
        }

        $record = $result['record'];

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'recordId' => $record->id]);
        }

        return redirect()
            ->route('delivery-locations.show', $record->id)
            ->with('success', 'Delivery location created.');
    }

    public function show(DeliveryLocation $deliveryLocation)
    {
        return Inertia::render('Tenant/DeliveryLocation/Show', [
            'record' => $deliveryLocation->load('subsidiary'),
        ]);
    }

    public function edit(DeliveryLocation $deliveryLocation)
    {
        return Inertia::render('Tenant/DeliveryLocation/Edit', [
            'record' => $deliveryLocation->load('subsidiary'),
        ]);
    }

    public function update(Request $request, DeliveryLocation $deliveryLocation)
    {
        $result = (new UpdateDeliveryLocation)($deliveryLocation->id, $request->all());

        if (! ($result['success'] ?? true)) {
            return back()->withErrors(['general' => $result['message'] ?? 'Failed to save.'])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('delivery-locations.show', $deliveryLocation->id)
            ->with('success', 'Delivery location updated.');
    }

    public function destroy(DeliveryLocation $deliveryLocation)
    {
        (new DeleteDeliveryLocation)($deliveryLocation->id);

        return redirect()
            ->route('delivery-locations.index')
            ->with('success', 'Delivery location deleted.');
    }

    /**
     * Lookup endpoint consumed by RecordSelect when the caller passes ?type=delivery_location.
     * Returns `{ records: [{ id, display_name, ... }] }`.
     */
    public function options(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $query = DeliveryLocation::query()->where('active', true);

        if ($search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($sub) use ($like) {
                $sub->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(city, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(state, \'\')) LIKE ?', [$like]);
            });
        }

        $records = $query
            ->orderBy('name')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json([
            'records' => collect($records->items())->map(fn ($r) => [
                'id' => $r->id,
                'display_name' => $r->display_name,
                'name' => $r->name,
                'city' => $r->city,
                'state' => $r->state,
            ]),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }
}
