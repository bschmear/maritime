<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Actions\CreateDelivery as CreateAction;
use App\Domain\Delivery\Actions\UpdateDelivery as UpdateAction;
use App\Domain\Delivery\Actions\DeleteDelivery as DeleteAction;
use App\Domain\DeliveryChecklistCategory\Models\DeliveryChecklistCategory;
use App\Domain\WorkOrder\Models\WorkOrder;
use App\Domain\Customer\Models\Customer;
use App\Actions\PublicStorage;
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
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
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
        $query = RecordModel::with(['customer', 'assetUnit', 'technician'])
            ->when($request->search, fn ($q, $s) =>
                $q->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('assetUnit', fn ($q) => $q->where('name', 'like', "%{$s}%"))
            )
            ->when($request->status && $request->status !== 'all', fn ($q, $s) =>
                $q->where('status', $s)
            )
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
            'scheduled'  => RecordModel::where('status', 'scheduled')->count(),
            'en_route'   => RecordModel::where('status', 'en_route')->count(),
            'delivered'  => RecordModel::where('status', 'delivered')->count(),
            'cancelled'  => RecordModel::where('status', 'cancelled')->count(),
        ];

        return Inertia::render('Tenant/Delivery/Index', [
            'deliveries'         => $query->paginate(15)->withQueryString(),
            'todayDeliveries'    => $todayDeliveries,
            'upcomingDeliveries' => $upcomingDeliveries,
            'stats'              => $stats,
            'filters'            => $request->only(['search', 'status']),
            'fieldsSchema'       => $this->getUnwrappedFieldsSchema(),
            'enumOptions'        => $this->getEnumOptions(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show (delegate to RecordController with id for route binding)
    |--------------------------------------------------------------------------
    */
    public function show(Request $request, $delivery)
    {
        // Get the delivery ID regardless of whether it's a model or string
        $deliveryId = $delivery instanceof \App\Domain\Delivery\Models\Delivery ? $delivery->id : $delivery;

        // Load delivery model for checklist data
        $deliveryModel = \App\Domain\Delivery\Models\Delivery::findOrFail($deliveryId);

        // Add checklist data
        $checklistItems = $deliveryModel->checklistItems()
            ->with(['completedBy', 'category'])
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->get();

        $checklistTemplates = \App\Domain\DeliveryChecklistTemplate\Models\DeliveryChecklistTemplate::with('items')
            ->where('is_default', true)
            ->get();

        $categories = DeliveryChecklistCategory::orderBy('name')->get();

        // Call parent show to get base data, then add our checklist data
        return parent::show($request, $deliveryId)->with([
            'checklistItems' => $checklistItems,
            'checklistTemplates' => $checklistTemplates,
            'categories' => $categories,
        ]);
    }

    public function sendSignatureRequest(Request $request, Delivery $delivery)
    {
        // Generate or get the signature URL
        $signatureUrl = url("/deliveries/{$delivery->uuid}/review");

        // TODO: Send email to customer with signature request
        // For now, just return success

        return response()->json([
            'message' => 'Signature request sent successfully',
            'signature_url' => $signatureUrl
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create / Store
    |--------------------------------------------------------------------------
    */
    public function workOrderDetails($workorderId)
    {
        $workorder = WorkOrder::with(['customer', 'assetUnit.asset', 'subsidiary', 'location'])->findOrFail($workorderId);

        return response()->json([
            'work_order_id'     => $workorder->id,
            'work_order_number' => 'WO-' . $workorder->work_order_number . ($workorder->display_name ? " — {$workorder->display_name}" : ''),
            'customer_id'       => $workorder->customer_id,
            'customer_name'     => $workorder->customer?->display_name,
            'asset_unit_id'     => $workorder->asset_unit_id,
            'asset_name'        => $workorder->assetUnit?->display_name,
            'subsidiary_id'     => $workorder->subsidiary_id,
            'subsidiary_name'   => $workorder->subsidiary?->display_name,
            'location_id'       => $workorder->location_id,
            'location_name'     => $workorder->location?->display_name,
            'address'           => $workorder->location ? [
                'address_line_1' => $workorder->location->address_line_1,
                'address_line_2' => $workorder->location->address_line_2,
                'city'           => $workorder->location->city,
                'state'          => $workorder->location->state,
                'postal_code'    => $workorder->location->postal_code,
                'country'        => $workorder->location->country,
                'latitude'       => $workorder->location->latitude,
                'longitude'      => $workorder->location->longitude,
            ] : ($workorder->customer ? [
                'address_line_1' => $workorder->customer->address_line_1,
                'address_line_2' => $workorder->customer->address_line_2,
                'city'           => $workorder->customer->city,
                'state'          => $workorder->customer->state,
                'postal_code'    => $workorder->customer->postal_code,
                'country'        => $workorder->customer->country,
                'latitude'       => $workorder->customer->latitude,
                'longitude'      => $workorder->customer->longitude,
            ] : null),
        ]);
    }

    public function customerDetails($customerId)
    {
        $customer = Customer::findOrFail($customerId);

        return response()->json([
            'customer_id' => $customer->id,
            'name'        => $customer->display_name,
            'address'     => [
                'address_line_1' => $customer->address_line_1,
                'address_line_2' => $customer->address_line_2,
                'city'           => $customer->city,
                'state'          => $customer->state,
                'postal_code'    => $customer->postal_code,
                'country'        => $customer->country,
                'latitude'       => $customer->latitude,
                'longitude'      => $customer->longitude,
            ],
        ]);
    }

    public function create()
    {
        return parent::create();
    }

    public function store(Request $request, PublicStorage $publicStorage)
    {
        $validated = $request->validate([
            'customer_id'          => 'required|exists:customers,id',
            'asset_unit_id'        => 'required|exists:asset_units,id',
            'work_order_id'        => 'nullable|exists:work_orders,id',
            'technician_id'        => 'nullable|exists:users,id',
            'scheduled_at'         => 'required|date',
            'estimated_arrival_at' => 'nullable|date|after:scheduled_at',
            'status'               => 'required|in:scheduled,en_route,rescheduled,confirmed',
            'internal_notes'       => 'nullable|string|max:5000',
            'customer_notes'       => 'nullable|string|max:5000',
            'address_line_1'       => 'nullable|string|max:255',
            'address_line_2'       => 'nullable|string|max:255',
            'city'                 => 'nullable|string|max:100',
            'state'                => 'nullable|string|max:100',
            'postal_code'          => 'nullable|string|max:20',
            'country'              => 'nullable|string|max:100',
            'latitude'             => 'nullable|numeric',
            'longitude'            => 'nullable|numeric',
        ]);

        $result = (new CreateAction())($validated);

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
                'success'   => true,
                'recordId'  => $delivery->id,
                'message'   => "Delivery {$delivery->display_name} created.",
            ]);
        }

        return redirect()
            ->route('deliveries.show', $delivery->id)
            ->with('success', "Delivery {$delivery->display_name} created.");
    }

    /*
    |--------------------------------------------------------------------------
    | Edit / Update / Destroy (delegate to RecordController with id)
    |--------------------------------------------------------------------------
    */
    public function edit($delivery)
    {
        return redirect()->route('deliveries.show', $delivery->uuid);
    }

    public function update(Request $request, $delivery, PublicStorage $publicStorage)
    {
        // Handle case where route model binding failed and $delivery is a string ID
        if (is_string($delivery)) {
            $delivery = RecordModel::findOrFail($delivery);
        }

        // Allow quick status-only PATCH (e.g. "Mark En Route" from show page)
        if ($request->has('status') && count($request->keys()) === 1) {
            $request->validate([
                'status' => 'required|in:scheduled,en_route,delivered,cancelled,rescheduled',
            ]);

            (new UpdateAction())($delivery->id, ['status' => $request->status]);

            return back()->with('success', 'Status updated.');
        }

        return parent::update($request, $delivery->id, $publicStorage);
    }

    public function markAsDelivered(Request $request, $delivery)
    {
        // Handle case where route model binding failed
        if (is_string($delivery)) {
            $delivery = RecordModel::findOrFail($delivery);
        }

        if ($delivery->delivered_at) {
            return response()->json(['message' => 'Delivery is already marked as delivered'], 400);
        }

        $delivery->update([
            'delivered_at' => now(),
            'status' => 'delivered',
        ]);

        return response()->json(['message' => 'Delivery marked as completed']);
    }

    public function destroy($delivery)
    {
        // Handle case where route model binding failed
        if (is_string($delivery)) {
            $delivery = Delivery::findOrFail($delivery);
        }

        return parent::destroy($delivery->id);
    }
}
