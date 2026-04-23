<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\Customer\Models\Customer;
use App\Domain\Delivery\Actions\ComputeDeliveryTravelEstimates;
use App\Domain\Delivery\Actions\CreateDelivery as CreateAction;
use App\Domain\Delivery\Actions\DeleteDelivery as DeleteAction;
use App\Domain\Delivery\Actions\MarkDeliveryItemDelivered;
use App\Domain\Delivery\Actions\UpdateDelivery as UpdateAction;
use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Models\DeliveryItem;
use App\Domain\DeliveryChecklistCategory\Models\DeliveryChecklistCategory;
use App\Domain\Location\Models\Location;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $query = RecordModel::with(['customer', 'assetUnit', 'technician'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                ->orWhereHas('assetUnit', fn ($q) => $q->where('name', 'like', "%{$s}%"))
            )
            ->when($statusesForQuery !== null, fn ($q) => $q->whereIn('status', $statusesForQuery))
            ->latest('scheduled_at');

        $todayDeliveries = RecordModel::with(['customer', 'assetUnit', 'technician'])
            ->whereDate('scheduled_at', today())
            ->orderBy('scheduled_at')
            ->get();

        $upcomingDeliveries = RecordModel::with(['customer', 'assetUnit', 'technician'])
            ->where('scheduled_at', '>', now()->endOfDay())
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        $stats = [
            'scheduled' => RecordModel::where('status', 'scheduled')->count(),
            'en_route' => RecordModel::where('status', 'en_route')->count(),
            'delivered' => RecordModel::where('status', 'delivered')->count(),
            'cancelled' => RecordModel::where('status', 'cancelled')->count(),
        ];

        return Inertia::render('Tenant/Delivery/Index', [
            'deliveries' => $query->paginate(15)->withQueryString(),
            'todayDeliveries' => $todayDeliveries,
            'upcomingDeliveries' => $upcomingDeliveries,
            'stats' => $stats,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $statusesForQuery === null ? 'all' : $statusesForQuery,
            ],
            'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
            'enumOptions' => $this->getEnumOptions(),
        ]);
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

    public function store(Request $request, PublicStorage $publicStorage)
    {
        $payload = $request->all();

        $result = (new CreateAction)($payload);

        if (! ($result['success'] ?? true)) {
            $errorMsg = $result['message'] ?? 'Failed to create delivery';
            if ($request->wantsJson() || $request->header('X-Modal-Request')) {
                return response()->json(['message' => $errorMsg, 'errors' => ['general' => [$errorMsg]]], 422);
            }

            return back()->withErrors(['general' => $errorMsg])->withInput();
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
            if ($request->wantsJson() || $request->header('X-Modal-Request')) {
                return response()->json(['message' => $errorMsg, 'errors' => ['general' => [$errorMsg]]], 422);
            }

            return back()->withErrors(['general' => $errorMsg])->withInput();
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

    protected function deliveryDetailRelationships(): array
    {
        return [
            'customer' => function ($q) {
                $q->with('contact');
            },
            'subsidiary',
            'location',
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
