<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\Customer\Models\Customer;
use App\Domain\Delivery\Actions\ComputeDeliveryTravelEstimates;
use App\Domain\Delivery\Actions\CreateDelivery as CreateAction;
use App\Domain\Delivery\Actions\DeleteDelivery as DeleteAction;
use App\Domain\Delivery\Actions\MarkDeliveryItemDelivered;
use App\Domain\Delivery\Actions\SwapDeliveryFleetAssignments;
use App\Domain\Delivery\Actions\UpdateDelivery as UpdateAction;
use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Models\DeliveryItem;
use App\Domain\Delivery\Support\DeliveryFleetFieldValidator;
use App\Domain\Delivery\Support\DeliveryFleetOccupancy;
use App\Domain\Fleet\Models\Fleet;
use App\Domain\DeliveryChecklistCategory\Models\DeliveryChecklistCategory;
use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\User\Models\User;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Models\AccountSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class DeliveryController extends RecordController
{
    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'deliveries',
            'Delivery',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'Delivery'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $allowedStatuses = ['scheduled', 'confirmed', 'en_route', 'delivered', 'cancelled', 'rescheduled'];
        $defaultStatuses = ['scheduled', 'en_route', 'rescheduled'];

        $statusesForQuery = $this->resolveDeliveryIndexStatuses($request, $allowedStatuses, $defaultStatuses);

        $deliveryIndexWith = [
            'customer',
            'assetUnit.asset',
            'location',
            'deliveryLocation',
            'technician',
            'items' => fn ($q) => $q->orderBy('position')->with(['assetUnit.asset', 'assetUnit.assetVariant', 'assetVariant']),
        ];

        $calendarMonthParam = $request->input('calendar_month');
        $monthStart = now()->copy()->startOfMonth();
        if (is_string($calendarMonthParam) && preg_match('/^\d{4}-\d{2}$/', $calendarMonthParam)) {
            try {
                $monthStart = \Carbon\Carbon::createFromFormat('Y-m', $calendarMonthParam, config('app.timezone'))->startOfMonth();
            } catch (\Throwable) {
                $monthStart = now()->copy()->startOfMonth();
            }
        }
        $monthEnd = $monthStart->copy()->endOfMonth();

        $calendarMonthQuery = RecordModel::with($deliveryIndexWith);
        $this->applyDeliveryIndexSubsidiaryLocationFilters($calendarMonthQuery, $request);
        $calendarMonthDeliveries = $calendarMonthQuery
            ->whereBetween('scheduled_at', [$monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()])
            ->orderBy('scheduled_at')
            ->get();

        $query = RecordModel::with($deliveryIndexWith);
        $this->applyDeliveryIndexSubsidiaryLocationFilters($query, $request);
        $query
            ->when($request->search, function ($q, $s) {
                $like = '%'.addcslashes($s, '%_\\').'%';
                $q->where(function ($q) use ($like) {
                    $q->whereHas('customer', fn ($q) => $q->where('name', 'like', $like))
                        ->orWhereHas('assetUnit', fn ($q) => $q->where('name', 'like', $like))
                        ->orWhereHas('items', fn ($q) => $q->where('name', 'like', $like));
                });
            })
            ->when($statusesForQuery !== null, fn ($q) => $q->whereIn('status', $statusesForQuery))
            ->latest('scheduled_at');

        $todayQuery = RecordModel::with($deliveryIndexWith);
        $this->applyDeliveryIndexSubsidiaryLocationFilters($todayQuery, $request);
        $todayDeliveries = $todayQuery
            ->whereDate('scheduled_at', today())
            ->orderBy('scheduled_at')
            ->get();

        $upcomingQuery = RecordModel::with($deliveryIndexWith);
        $this->applyDeliveryIndexSubsidiaryLocationFilters($upcomingQuery, $request);
        $upcomingDeliveries = $upcomingQuery
            ->where('scheduled_at', '>', now()->endOfDay())
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        $statsBase = function () use ($request) {
            $q = RecordModel::query();
            $this->applyDeliveryIndexSubsidiaryLocationFilters($q, $request);

            return $q;
        };
        $stats = [
            'scheduled' => $statsBase()->where('status', 'scheduled')->count(),
            'en_route' => $statsBase()->where('status', 'en_route')->count(),
            'delivered' => $statsBase()->where('status', 'delivered')->count(),
            'cancelled' => $statsBase()->where('status', 'cancelled')->count(),
        ];

        $locations = Location::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        $subsidiaries = Subsidiary::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        $paginationAppends = array_merge($request->query(), [
            'calendar_month' => $monthStart->format('Y-m'),
        ]);

        return Inertia::render('Tenant/Delivery/Index', [
            'deliveries' => $query->paginate(15)->appends($paginationAppends),
            'todayDeliveries' => $todayDeliveries,
            'upcomingDeliveries' => $upcomingDeliveries,
            'stats' => $stats,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $statusesForQuery === null ? 'all' : $statusesForQuery,
                'subsidiary_id' => $request->input('subsidiary_id') ? (int) $request->input('subsidiary_id') : null,
                'location_id' => $request->input('location_id') ? (int) $request->input('location_id') : null,
                'calendar_month' => $monthStart->format('Y-m'),
            ],
            'calendarMonthDeliveries' => $calendarMonthDeliveries,
            'locationOptions' => $locations,
            'subsidiaryOptions' => $subsidiaries,
            'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
            'enumOptions' => $this->getEnumOptions(),
        ]);
    }

    /**
     * Scope deliveries index to subsidiary and/or location (default: no filter = all).
     */
    private function applyDeliveryIndexSubsidiaryLocationFilters(Builder $query, Request $request): void
    {
        if ($request->filled('subsidiary_id') && (int) $request->input('subsidiary_id') > 0) {
            $query->where('subsidiary_id', (int) $request->input('subsidiary_id'));
        }
        if ($request->filled('location_id') && (int) $request->input('location_id') > 0) {
            $query->where('location_id', (int) $request->input('location_id'));
        }
    }

    /**
     * @param  list<string>  $allowedStatuses
     * @param  list<string>  $defaultStatuses
     * @return list<string>|null null = no status filter (all statuses)
     */
    private function resolveDeliveryIndexStatuses(Request $request, array $allowedStatuses, array $defaultStatuses): ?array
    {
        $raw = $request->input('status');

        if ($raw === 'all') {
            return null;
        }

        if (is_array($raw)) {
            $picked = array_values(array_intersect($allowedStatuses, $raw));

            return count($picked) > 0 ? $picked : null;
        }

        if (is_string($raw) && $raw !== '') {
            return in_array($raw, $allowedStatuses, true) ? [$raw] : $defaultStatuses;
        }

        return $defaultStatuses;
    }

    /*
    |--------------------------------------------------------------------------
    | Show — custom, invoice-style page (no generic Form)
    |--------------------------------------------------------------------------
    */
    public function show(Request $request, $delivery)
    {
        $deliveryId = $delivery instanceof RecordModel ? $delivery->id : $delivery;

        $record = RecordModel::with($this->deliveryDetailRelationships())
            ->findOrFail($deliveryId);

        $checklistItems = $record->checklistItems()
            ->with(['completedBy', 'category'])
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->get();

        $checklistTemplates = \App\Domain\DeliveryChecklistTemplate\Models\DeliveryChecklistTemplate::with('items')
            ->where('is_default', true)
            ->get();

        $categories = DeliveryChecklistCategory::orderBy('name')->get();

        $account = \App\Models\AccountSettings::getCurrent();

        return Inertia::render('Tenant/Delivery/Show', [
            'record' => $record,
            'recordType' => 'deliveries',
            'recordTitle' => 'Delivery',
            'domainName' => 'Delivery',
            'enumOptions' => $this->getEnumOptions(),
            'account' => $account,
            'checklistItems' => $checklistItems,
            'checklistTemplates' => $checklistTemplates,
            'categories' => $categories,
            'customerAddresses' => $record->customer ? $record->customer->addresses()->get() : [],
        ]);
    }

    public function sendSignatureRequest(Request $request, RecordModel $delivery)
    {
        $signatureUrl = url("/deliveries/{$delivery->uuid}/review");

        return response()->json([
            'message' => 'Signature request sent successfully',
            'signature_url' => $signatureUrl,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create / Store — custom form
    |--------------------------------------------------------------------------
    */
    public function workOrderDetails($workorderId)
    {
        $workorder = WorkOrder::with(['customer', 'assetUnit.asset', 'subsidiary', 'location'])->findOrFail($workorderId);

        return response()->json([
            'work_order_id' => $workorder->id,
            'work_order_number' => 'WO-'.$workorder->work_order_number.($workorder->display_name ? " — {$workorder->display_name}" : ''),
            'customer_id' => $workorder->customer_id,
            'customer_name' => $workorder->customer?->display_name,
            'asset_unit_id' => $workorder->asset_unit_id,
            'asset_name' => $workorder->assetUnit?->display_name,
            'subsidiary_id' => $workorder->subsidiary_id,
            'subsidiary_name' => $workorder->subsidiary?->display_name,
            'location_id' => $workorder->location_id,
            'location_name' => $workorder->location?->display_name,
            'address' => $workorder->location ? [
                'address_line_1' => $workorder->location->address_line_1,
                'address_line_2' => $workorder->location->address_line_2,
                'city' => $workorder->location->city,
                'state' => $workorder->location->state,
                'postal_code' => $workorder->location->postal_code,
                'country' => $workorder->location->country,
                'latitude' => $workorder->location->latitude,
                'longitude' => $workorder->location->longitude,
            ] : ($workorder->customer ? [
                'address_line_1' => $workorder->customer->address_line_1,
                'address_line_2' => $workorder->customer->address_line_2,
                'city' => $workorder->customer->city,
                'state' => $workorder->customer->state,
                'postal_code' => $workorder->customer->postal_code,
                'country' => $workorder->customer->country,
                'latitude' => $workorder->customer->latitude,
                'longitude' => $workorder->customer->longitude,
            ] : null),
        ]);
    }

    public function customerDetails($customerId)
    {
        $customer = Customer::with('addresses')->findOrFail($customerId);

        return response()->json([
            'customer_id' => $customer->id,
            'contact_id' => $customer->contact_id,
            'name' => $customer->display_name,
            'address' => [
                'address_line_1' => $customer->address_line_1,
                'address_line_2' => $customer->address_line_2,
                'city' => $customer->city,
                'state' => $customer->state,
                'postal_code' => $customer->postal_code,
                'country' => $customer->country,
                'latitude' => $customer->latitude,
                'longitude' => $customer->longitude,
            ],
            'addresses' => $customer->addresses,
        ]);
    }

    public function create()
    {
        $account = \App\Models\AccountSettings::getCurrent();
        $prefill = $this->deliveryCreatePrefill(request());

        $customerAddresses = [];
        if ($prefill && ! empty($prefill['customer_id'])) {
            $customer = Customer::query()->find($prefill['customer_id']);
            if ($customer) {
                $customerAddresses = $customer->addresses()->get()->all();
            }
        }

        return Inertia::render('Tenant/Delivery/Create', [
            'recordType' => 'deliveries',
            'recordTitle' => 'Delivery',
            'domainName' => 'Delivery',
            'enumOptions' => $this->getEnumOptions(),
            'account' => $account,
            'prefill' => $prefill,
            'customerAddresses' => $customerAddresses,
        ]);
    }

    public function schedule()
    {
        $account = \App\Models\AccountSettings::getCurrent();
        $locations = Location::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        return Inertia::render('Tenant/Delivery/Schedule', [
            'recordType' => 'deliveries',
            'recordTitle' => 'Delivery Schedule',
            'domainName' => 'Delivery',
            'enumOptions' => $this->getEnumOptions(),
            'account' => $account,
            'locationOptions' => $locations,
        ]);
    }

    public function store(Request $request, PublicStorage $publicStorage)
    {
        $payload = $request->all();

        $result = (new CreateAction)($payload);

        if (! ($result['success'] ?? true)) {
            $errorMsg = $result['message'] ?? 'Failed to create delivery';
            $conflicts = $result['conflicts'] ?? [];
            if ($request->wantsJson() || $request->header('X-Modal-Request')) {
                return response()->json([
                    'message' => $errorMsg,
                    'errors' => ['general' => [$errorMsg]],
                    'conflicts' => $conflicts,
                ], 422);
            }

            return back()
                ->withErrors(['general' => $errorMsg])
                ->with('delivery_fleet_conflicts', $conflicts)
                ->withInput();
        }

        $delivery = $result['record'];

        if ($request->wantsJson() || $request->header('X-Modal-Request')) {
            return response()->json([
                'success' => true,
                'recordId' => $delivery->id,
                'message' => "Delivery {$delivery->display_name} created.",
            ]);
        }

        return redirect()
            ->route('deliveries.show', $delivery->id)
            ->with('success', "Delivery {$delivery->display_name} created.");
    }

    /*
    |--------------------------------------------------------------------------
    | Edit / Update / Destroy — custom form
    |--------------------------------------------------------------------------
    */
    public function edit($delivery)
    {
        $deliveryId = $delivery instanceof RecordModel ? $delivery->id : $delivery;

        $record = RecordModel::with($this->deliveryDetailRelationships())
            ->findOrFail($deliveryId);

        $account = \App\Models\AccountSettings::getCurrent();

        return Inertia::render('Tenant/Delivery/Edit', [
            'record' => $record,
            'recordType' => 'deliveries',
            'recordTitle' => 'Delivery',
            'domainName' => 'Delivery',
            'enumOptions' => $this->getEnumOptions(),
            'account' => $account,
            'customerAddresses' => $record->customer ? $record->customer->addresses()->get() : [],
        ]);
    }

    public function update(Request $request, $delivery, PublicStorage $publicStorage)
    {
        if (is_string($delivery) || is_numeric($delivery)) {
            $delivery = RecordModel::findOrFail($delivery);
        }

        // Quick status-only PATCH (existing behavior preserved).
        if ($request->has('status') && count($request->keys()) === 1) {
            $request->validate([
                'status' => 'required|in:scheduled,en_route,delivered,cancelled,rescheduled,confirmed',
            ]);

            (new UpdateAction)($delivery->id, ['status' => $request->status]);

            return back()->with('success', 'Status updated.');
        }

        $result = (new UpdateAction)($delivery->id, $request->all());

        if (! ($result['success'] ?? true)) {
            $errorMsg = $result['message'] ?? 'Failed to update delivery';
            $conflicts = $result['conflicts'] ?? [];
            if ($request->wantsJson() || $request->header('X-Modal-Request')) {
                return response()->json([
                    'message' => $errorMsg,
                    'errors' => ['general' => [$errorMsg]],
                    'conflicts' => $conflicts,
                ], 422);
            }

            return back()
                ->withErrors(['general' => $errorMsg])
                ->with('delivery_fleet_conflicts', $conflicts)
                ->withInput();
        }

        if ($request->wantsJson() || $request->header('X-Modal-Request')) {
            return response()->json([
                'success' => true,
                'recordId' => $delivery->id,
                'message' => 'Delivery updated.',
            ]);
        }

        return redirect()
            ->route('deliveries.show', $delivery->id)
            ->with('success', 'Delivery updated.');
    }

    public function markAsDelivered(Request $request, $delivery)
    {
        if (is_string($delivery) || is_numeric($delivery)) {
            $delivery = RecordModel::findOrFail($delivery);
        }

        if ($delivery->delivered_at) {
            return response()->json(['message' => 'Delivery is already marked as delivered'], 400);
        }

        // Mark every undelivered item so the status flip is consistent with per-item tracking.
        $delivery->items()->whereNull('delivered_at')->update([
            'delivered_at' => now(),
            'delivered_by_user_id' => optional($request->user())->id,
        ]);

        $delivery->load('items');
        $delivery->syncStatusFromItems();
        // Ensure the delivery reflects "delivered" even if it had no items.
        if ($delivery->status !== 'delivered') {
            $delivery->status = 'delivered';
            $delivery->delivered_at = $delivery->delivered_at ?: now();
        }
        $delivery->save();

        return response()->json(['message' => 'Delivery marked as completed']);
    }

    public function destroy($delivery)
    {
        if (is_string($delivery) || is_numeric($delivery)) {
            $delivery = RecordModel::findOrFail($delivery);
        }

        return parent::destroy($delivery->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Per-item endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Toggle a single delivery item's delivered state.
     */
    public function markItemDelivered(Request $request, $delivery, $item)
    {
        $deliveryId = $delivery instanceof RecordModel ? $delivery->id : $delivery;
        $itemId = $item instanceof DeliveryItem ? $item->id : $item;

        $deliveryItem = DeliveryItem::where('delivery_id', $deliveryId)
            ->whereKey($itemId)
            ->firstOrFail();

        $delivered = (bool) $request->input('delivered', true);

        (new MarkDeliveryItemDelivered)($deliveryItem, optional($request->user())->id, $delivered);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', $delivered ? 'Item delivered.' : 'Item marked as not delivered.');
    }

    /**
     * Preview what items would be synced from a given source (work order or transaction).
     * Used by the create/edit form before the delivery is saved.
     */
    public function checkFleetSchedule(Request $request): JsonResponse
    {
        $payload = $request->all();
        $validator = Validator::make($payload, [
            'scheduled_at' => 'required|date',
            'time_to_leave_by' => 'nullable|date',
            'estimated_travel_duration_seconds' => 'nullable|integer|min:0|max:864000',
            'delivery_duration_minutes' => 'nullable|integer|min:1|max:32767',
            'fleet_truck_id' => 'nullable|integer|exists:fleets,id',
            'fleet_trailer_id' => 'nullable|integer|exists:fleets,id',
            'exclude_delivery_id' => 'nullable|integer|exists:deliveries,id',
            'location_id' => 'nullable|integer|exists:locations,id',
        ]);
        $validator->after(function ($v) use ($payload) {
            DeliveryFleetFieldValidator::validateFleetRows($v, $payload);
        });
        $validated = $validator->validate();

        $subject = DeliveryFleetOccupancy::deliveryFromAttributes($validated, $validated['exclude_delivery_id'] ?? null);
        $truckId = isset($validated['fleet_truck_id']) ? (int) $validated['fleet_truck_id'] : null;
        $trailerId = isset($validated['fleet_trailer_id']) ? (int) $validated['fleet_trailer_id'] : null;
        if ($truckId <= 0) {
            $truckId = null;
        }
        if ($trailerId <= 0) {
            $trailerId = null;
        }

        $conflicts = DeliveryFleetOccupancy::findConflicts($truckId, $trailerId, $subject, null);

        return response()->json([
            'ok' => $conflicts === [],
            'conflicts' => $conflicts,
        ]);
    }

    public function scheduleBoard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'location_id' => 'nullable|integer|exists:locations,id',
        ]);

        $account = AccountSettings::getCurrent();
        $tz = ($account?->timezone) ?: (string) config('app.timezone');

        $dayStart = Carbon::parse($validated['date'], $tz)->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay();

        $technicians = User::query()
            ->where('is_technician', true)
            ->orderBy('display_name')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->display_name ?: trim($u->first_name.' '.$u->last_name) ?: $u->email,
            ])
            ->values();

        $deliveryQuery = RecordModel::query()
            ->with(['customer.contact', 'location', 'deliveryLocation', 'fleetTruck', 'fleetTrailer'])
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->whereNotNull('technician_id');

        if (! empty($validated['location_id'])) {
            $deliveryQuery->where('location_id', (int) $validated['location_id']);
        }

        $deliveries = $deliveryQuery->orderBy('scheduled_at')->get();

        /** @var array<string, list<array<string, mixed>>> $byTechnician */
        $byTechnician = [];
        foreach ($technicians as $row) {
            $byTechnician[(string) $row['id']] = [];
        }
        foreach ($deliveries as $d) {
            $tid = (string) $d->technician_id;
            if (! isset($byTechnician[$tid])) {
                $byTechnician[$tid] = [];
            }
            $byTechnician[$tid][] = $this->scheduleBoardDeliveryBlock($d, $validated['date'], $tz);
        }

        return response()->json([
            'date' => $validated['date'],
            'timezone' => $tz,
            'technicians' => $technicians,
            'deliveriesByTechnician' => $byTechnician,
        ]);
    }

    public function swapFleet(Request $request, $delivery): JsonResponse
    {
        $deliveryModel = $delivery instanceof RecordModel ? $delivery : RecordModel::query()->findOrFail($delivery);
        $otherId = (int) $request->validate(['other_delivery_id' => 'required|integer|exists:deliveries,id'])['other_delivery_id'];

        $result = (new SwapDeliveryFleetAssignments)((int) $deliveryModel->id, $otherId);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Swap failed.',
            ], 422);
        }

        /** @var RecordModel $a */
        /** @var RecordModel $b */
        $a = $result['records'][0];
        $b = $result['records'][1];

        return response()->json([
            'success' => true,
            'delivery_a' => [
                'id' => $a->id,
                'fleet_truck_id' => $a->fleet_truck_id,
                'fleet_trailer_id' => $a->fleet_trailer_id,
            ],
            'delivery_b' => [
                'id' => $b->id,
                'fleet_truck_id' => $b->fleet_truck_id,
                'fleet_trailer_id' => $b->fleet_trailer_id,
            ],
        ]);
    }

    public function travelEstimate(Request $request, ComputeDeliveryTravelEstimates $compute): JsonResponse
    {

        // "location_id" => 8
        // "scheduled_at" => "2026-04-28T21:00"
        // "address_line_1" => "333 E Wisconsin Ave"
        // "address_line_2" => null
        // "city" => "Milwaukee"
        // "state" => "WI"
        // "postal_code" => "53202"
        // "country" => "United States"
        // "latitude" => "43.0384673"
        // "longitude" => "-87.9067361"

        $v = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'scheduled_at' => 'required|date',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $location = Location::query()->findOrFail($v['location_id']);
        // dd($location);
        $result = $compute->previewFromInputs($location, [
            'address_line_1' => $v['address_line_1'] ?? null,
            'address_line_2' => $v['address_line_2'] ?? null,
            'city' => $v['city'] ?? null,
            'state' => $v['state'] ?? null,
            'postal_code' => $v['postal_code'] ?? null,
            'country' => $v['country'] ?? null,
            'latitude' => $v['latitude'] ?? null,
            'longitude' => $v['longitude'] ?? null,
        ], $v['scheduled_at']);

        if ($result === null) {
            return response()->json(['ok' => false, 'message' => 'Could not compute travel time. Check addresses and API key.']);
        }

        return response()->json(array_merge(['ok' => true], $result));
    }

    public function markEnRoute(Request $request, $delivery)
    {
        $id = $delivery instanceof RecordModel ? $delivery->id : (int) $delivery;
        $model = RecordModel::query()->findOrFail($id);

        if (! in_array($model->status, ['scheduled', 'confirmed', 'rescheduled'], true)) {
            return back()->with('error', 'This delivery cannot be marked en route from its current status.');
        }

        $result = (new UpdateAction)($id, ['status' => 'en_route']);

        if (! ($result['success'] ?? true)) {
            return back()->with('error', $result['message'] ?? 'Update failed.');
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['success' => true, 'record' => $result['record'] ?? $model->fresh()]);
        }

        return redirect()
            ->route('deliveries.show', $id)
            ->with('success', 'Marked en route. Estimated arrival time updated.');
    }

    public function sourceItems(Request $request)
    {
        $request->validate([
            'type' => 'required|in:transaction,work_order,workorder',
            'id' => 'required|integer',
        ]);

        $type = strtolower($request->input('type'));
        $id = (int) $request->input('id');

        if ($type === 'transaction') {
            $source = Transaction::with([
                'items.assetUnit.asset',
                'items.assetVariant',
                'customer.contact',
                'subsidiary',
                'location',
            ])->find($id);

            if (! $source) {
                return response()->json(['items' => []]);
            }

            $items = collect($source->items)
                ->filter(fn ($i) => ! empty($i->asset_unit_id))
                ->values()
                ->map(function ($i) {
                    return [
                        'asset_unit_id' => $i->asset_unit_id,
                        'asset_variant_id' => $i->asset_variant_id,
                        'name' => $i->name ?? $i->assetUnit?->display_name ?? 'Asset',
                        'description' => $i->description,
                        'quantity' => 1,
                        'unit_price' => (float) ($i->unit_price ?? 0),
                        'asset_unit' => $i->assetUnit,
                        'asset_variant' => $i->assetVariant,
                    ];
                });

            return response()->json([
                'items' => $items,
                'customer_id' => $source->customer_id,
                'subsidiary_id' => $source->subsidiary_id,
                'location_id' => $source->location_id,
                'subsidiary' => $source->subsidiary ? [
                    'id' => $source->subsidiary->id,
                    'display_name' => $source->subsidiary->display_name,
                ] : null,
                'location' => $source->location ? [
                    'id' => $source->location->id,
                    'display_name' => $source->location->display_name,
                ] : null,
                'source' => [
                    'id' => $source->id,
                    'display_name' => $source->display_name,
                ],
            ]);
        }

        // work_order / workorder
        $source = WorkOrder::with(['assetUnit.asset', 'customer', 'subsidiary', 'location'])->find($id);
        if (! $source || ! $source->assetUnit) {
            return response()->json(['items' => []]);
        }

        $unit = $source->assetUnit;

        return response()->json([
            'items' => [[
                'asset_unit_id' => $unit->id,
                'asset_variant_id' => $unit->asset_variant_id ?? null,
                'name' => $unit->display_name ?? 'Asset',
                'description' => null,
                'quantity' => 1,
                'unit_price' => 0,
                'asset_unit' => $unit,
                'asset_variant' => null,
            ]],
            'customer_id' => $source->customer_id,
            'subsidiary_id' => $source->subsidiary_id,
            'location_id' => $source->location_id,
            'subsidiary' => $source->subsidiary ? [
                'id' => $source->subsidiary->id,
                'display_name' => $source->subsidiary->display_name,
            ] : null,
            'location' => $source->location ? [
                'id' => $source->location->id,
                'display_name' => $source->location->display_name,
            ] : null,
            'source' => [
                'id' => $source->id,
                'display_name' => 'WO-'.($source->work_order_number ?? $source->id),
            ],
        ]);
    }

    /**
     * Printable page — delivery document + a separate checklist page.
     */
    public function print(Request $request, $delivery)
    {
        $deliveryId = $delivery instanceof RecordModel ? $delivery->id : $delivery;

        $record = RecordModel::with($this->deliveryDetailRelationships())
            ->findOrFail($deliveryId);

        $checklistItems = $record->checklistItems()
            ->with(['category'])
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->get();

        $account = \App\Models\AccountSettings::getCurrent();

        return Inertia::render('Tenant/Delivery/Print', [
            'record' => $record,
            'account' => $account,
            'checklistItems' => $checklistItems,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Build initial delivery form state when opening create with ?transaction_id= or ?work_order_id=.
     *
     * @return array<string, mixed>|null
     */
    protected function deliveryCreatePrefill(Request $request): ?array
    {
        if ($request->filled('transaction_id')) {
            $source = Transaction::with([
                'items.assetUnit.asset',
                'items.assetVariant',
                'customer.contact',
                'subsidiary',
                'location',
            ])->find((int) $request->input('transaction_id'));

            if (! $source) {
                return null;
            }

            $items = collect($source->items)
                ->filter(fn ($i) => ! empty($i->asset_unit_id))
                ->values()
                ->map(function ($i) {
                    return [
                        'asset_unit_id' => $i->asset_unit_id,
                        'asset_variant_id' => $i->asset_variant_id,
                        'name' => $i->name ?? $i->assetUnit?->display_name ?? 'Asset',
                        'description' => $i->description,
                        'quantity' => 1,
                        'unit_price' => (float) ($i->unit_price ?? 0),
                        'asset_unit' => $i->assetUnit,
                        'asset_variant' => $i->assetVariant,
                    ];
                })
                ->all();

            return [
                'customer_id' => $source->customer_id,
                'transaction_id' => $source->id,
                'subsidiary_id' => $source->subsidiary_id,
                'location_id' => $source->location_id,
                'customer' => [
                    'id' => $source->customer_id,
                    'display_name' => $source->customer?->display_name,
                    'contact' => $source->customer?->contact ? [
                        'display_name' => $source->customer->contact->display_name,
                    ] : null,
                ],
                'transaction' => [
                    'id' => $source->id,
                    'display_name' => $source->display_name,
                ],
                'subsidiary' => $source->subsidiary ? [
                    'id' => $source->subsidiary->id,
                    'display_name' => $source->subsidiary->display_name,
                ] : null,
                'location' => $source->location ? [
                    'id' => $source->location->id,
                    'display_name' => $source->location->display_name,
                ] : null,
                'items' => $items,
            ];
        }

        if ($request->filled('work_order_id')) {
            $source = WorkOrder::with(['assetUnit.asset', 'customer.contact', 'subsidiary', 'location'])->find((int) $request->input('work_order_id'));

            if (! $source || ! $source->assetUnit) {
                return null;
            }

            $unit = $source->assetUnit;

            return [
                'customer_id' => $source->customer_id,
                'work_order_id' => $source->id,
                'subsidiary_id' => $source->subsidiary_id,
                'location_id' => $source->location_id,
                'customer' => [
                    'id' => $source->customer_id,
                    'display_name' => $source->customer?->display_name,
                    'contact' => $source->customer?->contact ? [
                        'display_name' => $source->customer->contact->display_name,
                    ] : null,
                ],
                'work_order' => [
                    'id' => $source->id,
                    'display_name' => 'WO-'.($source->work_order_number ?? $source->id),
                ],
                'subsidiary' => $source->subsidiary ? [
                    'id' => $source->subsidiary->id,
                    'display_name' => $source->subsidiary->display_name,
                ] : null,
                'location' => $source->location ? [
                    'id' => $source->location->id,
                    'display_name' => $source->location->display_name,
                ] : null,
                'items' => [[
                    'asset_unit_id' => $unit->id,
                    'asset_variant_id' => $unit->asset_variant_id ?? null,
                    'name' => $unit->display_name ?? 'Asset',
                    'description' => null,
                    'quantity' => 1,
                    'unit_price' => 0,
                    'asset_unit' => $unit,
                    'asset_variant' => null,
                ]],
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleBoardDeliveryBlock(RecordModel $d, string $boardDateYmd, string $tz): array
    {
        /** Minutes from start of calendar day in $tz (DeliveryScheduler can window any sub-range). */
        $gridStartHour = 0;

        $estimatedTravelSeconds = $d->estimated_travel_duration_seconds;
        if (($estimatedTravelSeconds === null || (int) $estimatedTravelSeconds <= 0)
            && $d->time_to_leave_by
            && $d->scheduled_at) {
            $delta = $d->scheduled_at->getTimestamp() - $d->time_to_leave_by->getTimestamp();
            if ($delta > 0) {
                $estimatedTravelSeconds = $delta;
            }
        }

        $scheduledLocal = $d->scheduled_at?->copy()->timezone($tz);
        $gridOrigin = Carbon::parse($boardDateYmd.' '.$gridStartHour.':00:00', $tz);
        $blockStartMinutes = null;
        if ($scheduledLocal) {
            $blockStartMinutes = (int) floor(($scheduledLocal->getTimestamp() - $gridOrigin->getTimestamp()) / 60);
        }

        return [
            'id' => $d->id,
            'display_name' => $d->display_name,
            'customer_name' => $d->customer?->display_name ?? '—',
            'start_location' => $d->location?->display_name ?? '—',
            'end_location' => $this->deliveryDestinationShortLabel($d),
            'fleet_truck_id' => $d->fleet_truck_id,
            'fleet_trailer_id' => $d->fleet_trailer_id,
            'fleet_truck_label' => $this->scheduleBoardFleetUnitLabel($d->fleetTruck),
            'fleet_trailer_label' => $this->scheduleBoardFleetUnitLabel($d->fleetTrailer),
            'estimated_travel_duration_seconds' => $estimatedTravelSeconds !== null ? (int) $estimatedTravelSeconds : null,
            'time_to_leave_by' => $d->time_to_leave_by?->copy()->timezone($tz)->toIso8601String(),
            'scheduled_at' => $d->scheduled_at?->copy()->timezone($tz)->toIso8601String(),
            'block_start_minutes' => $blockStartMinutes,
            'delivery_duration_minutes' => $d->delivery_duration_minutes,
        ];
    }

    private function scheduleBoardFleetUnitLabel(?Fleet $unit): ?string
    {
        if ($unit === null) {
            return null;
        }
        $name = $unit->display_name;
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }
        $plate = $unit->license_plate;
        if (is_string($plate) && trim($plate) !== '') {
            return trim($plate);
        }

        return $unit->id ? 'Unit #'.$unit->id : null;
    }

    private function deliveryDestinationShortLabel(RecordModel $d): string
    {
        if ($d->delivery_to_type === 'delivery_location' && $d->deliveryLocation) {
            return $d->deliveryLocation->display_name
                ?? $d->deliveryLocation->name
                ?? '—';
        }
        $parts = array_filter([
            $d->address_line_1,
            $d->city,
            $d->state,
            $d->postal_code,
        ], fn ($v) => $v !== null && trim((string) $v) !== '');

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return '—';
    }

    protected function deliveryDetailRelationships(): array
    {
        return [
            'customer' => function ($q) {
                $q->with('contact');
            },
            'subsidiary',
            'location',
            'fleetTruck',
            'fleetTrailer',
            'assetUnit.asset',
            'assetUnit.assetVariant',
            'workOrder',
            'transaction',
            'technician',
            'deliveryLocation',
            'items' => function ($q) {
                $q->orderBy('position')
                    ->with([
                        'assetUnit.asset',
                        'assetUnit.assetVariant',
                        'assetVariant',
                        'deliveredBy',
                    ]);
            },
        ];
    }
}
