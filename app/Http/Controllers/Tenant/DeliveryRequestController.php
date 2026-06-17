<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Delivery\Actions\CreateDelivery;
use App\Domain\Delivery\Actions\CreateDeliveryRequest;
use App\Domain\Delivery\Actions\ResubmitDeliveryRequest;
use App\Domain\Delivery\Actions\ReviewDeliveryRequest;
use App\Domain\Delivery\Models\Delivery as RecordModel;
use App\Domain\Delivery\Support\DeliveryApproverResolver;
use App\Domain\Location\Models\Location;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Models\AccountSettings;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryRequestController extends Controller
{
    use HasSchemaSupport;

    protected string $domainName = 'Delivery';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        abort_unless(tenant_has_permission('delivery.view'), 403);

        $query = RecordModel::query()
            ->with(['customer', 'location', 'requestedBy', 'technician'])
            ->where('status', 'requested')
            ->orderByDesc('requested_at');

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->input('location_id'));
        }

        $userId = current_tenant_user_id();
        $isAdmin = current_tenant_role_slug() === 'admin';
        $canCreateDelivery = tenant_has_permission('delivery.create');

        if (! $canCreateDelivery && ! $isAdmin && $userId !== null) {
            $query->where('requested_by_user_id', $userId);
        }

        $deliveries = $query->paginate(25)->withQueryString();

        $locations = Location::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        return Inertia::render('Tenant/Delivery/Requests/Index', [
            'deliveries' => $deliveries,
            'filters' => [
                'location_id' => $request->input('location_id'),
            ],
            'locationOptions' => $locations,
            'canCreateDelivery' => $canCreateDelivery,
            'pendingCount' => RecordModel::query()->where('status', 'requested')->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless(tenant_has_permission('delivery.view'), 403);

        $account = AccountSettings::getCurrent();
        $prefill = [];
        if ($request->filled('transaction_id')) {
            $prefill['transaction_id'] = (int) $request->input('transaction_id');
        }
        if ($request->filled('work_order_id')) {
            $prefill['work_order_id'] = (int) $request->input('work_order_id');
        }

        return Inertia::render('Tenant/Delivery/Requests/Create', [
            'record' => $prefill ?: null,
            'enumOptions' => $this->getEnumOptions(),
            'account' => $account,
            'canCreateDelivery' => tenant_has_permission('delivery.create'),
            'approverLocationIds' => Location::query()
                ->with(['managerUser', 'deliveryApprover'])
                ->get()
                ->filter(fn (Location $location) => DeliveryApproverResolver::currentUserCanApprove($location))
                ->pluck('id')
                ->values()
                ->all(),
        ]);
    }

    public function store(
        Request $request,
        CreateDeliveryRequest $action,
        CreateDelivery $createDelivery,
        NotificationService $notifications,
    ): RedirectResponse {
        abort_unless(tenant_has_permission('delivery.view'), 403);

        if ($request->boolean('schedule_directly')) {
            return $this->storeDirectSchedule($request, $createDelivery);
        }

        $result = $action($request->all());

        if (! ($result['success'] ?? false)) {
            return back()
                ->withInput()
                ->withErrors(['form' => $result['message'] ?? 'Could not submit delivery request.']);
        }

        $delivery = $result['record'];
        $account = AccountSettings::getCurrent();
        $notifications->notifyDeliveryRequestSubmitted($delivery, $account);

        return redirect()
            ->route('deliveries.show', $delivery->id)
            ->with('success', 'Delivery request submitted for approval.');
    }

    public function approve(Request $request, RecordModel $delivery, ReviewDeliveryRequest $action, NotificationService $notifications): RedirectResponse
    {
        $result = $action($delivery, ReviewDeliveryRequest::DECISION_APPROVED, $request->input('review_notes'));

        return $this->handleReviewResult($result, $notifications, 'Delivery request approved and scheduled.');
    }

    public function deny(Request $request, RecordModel $delivery, ReviewDeliveryRequest $action, NotificationService $notifications): RedirectResponse
    {
        $request->validate(['review_notes' => 'nullable|string|max:5000']);

        $result = $action($delivery, ReviewDeliveryRequest::DECISION_DENIED, $request->input('review_notes'));

        return $this->handleReviewResult($result, $notifications, 'Delivery request denied.');
    }

    public function proposeReschedule(Request $request, RecordModel $delivery, ReviewDeliveryRequest $action, NotificationService $notifications): RedirectResponse
    {
        $request->validate([
            'proposed_scheduled_at' => 'required|date',
            'review_notes' => 'nullable|string|max:5000',
        ]);

        $result = $action(
            $delivery,
            ReviewDeliveryRequest::DECISION_RESCHEDULE_REQUESTED,
            $request->input('review_notes'),
            $request->input('proposed_scheduled_at'),
        );

        return $this->handleReviewResult($result, $notifications, 'Reschedule proposal sent to requester.');
    }

    public function resubmit(Request $request, RecordModel $delivery, ResubmitDeliveryRequest $action, NotificationService $notifications): RedirectResponse
    {
        $request->validate(['scheduled_at' => 'nullable|date']);

        $result = $action($delivery, $request->input('scheduled_at'));

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Could not resubmit delivery request.');
        }

        $account = AccountSettings::getCurrent();
        $notifications->notifyDeliveryRequestSubmitted($result['record'], $account);

        return back()->with('success', 'Delivery request resubmitted for approval.');
    }

    private function storeDirectSchedule(Request $request, CreateDelivery $createDelivery): RedirectResponse
    {
        $locationId = (int) $request->input('location_id');
        abort_if($locationId <= 0, 422);

        $location = Location::query()
            ->with(['managerUser', 'deliveryApprover'])
            ->findOrFail($locationId);

        abort_unless(
            DeliveryApproverResolver::currentUserCanApprove($location),
            403,
            'You cannot schedule deliveries for this location.',
        );

        $payload = $request->all();
        $payload['status'] = $payload['status'] ?? 'scheduled';

        $result = $createDelivery($payload);

        if (! ($result['success'] ?? false)) {
            $conflicts = $result['conflicts'] ?? [];
            if ($conflicts !== []) {
                return back()
                    ->withInput()
                    ->with('error', $result['message'] ?? 'Fleet scheduling conflict.')
                    ->with('delivery_fleet_conflicts', $conflicts);
            }

            return back()
                ->withInput()
                ->withErrors(['form' => $result['message'] ?? 'Could not schedule delivery.']);
        }

        return redirect()
            ->route('deliveries.show', $result['record']->id)
            ->with('success', 'Delivery scheduled.');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function handleReviewResult(array $result, NotificationService $notifications, string $successMessage): RedirectResponse
    {
        if (! ($result['success'] ?? false)) {
            $conflicts = $result['conflicts'] ?? [];
            if ($conflicts !== []) {
                return back()
                    ->with('error', $result['message'] ?? 'Fleet scheduling conflict.')
                    ->with('delivery_fleet_conflicts', $conflicts);
            }

            return back()->with('error', $result['message'] ?? 'Could not complete review.');
        }

        $account = AccountSettings::getCurrent();
        $notifications->notifyDeliveryRequestReviewed($result['record'], $account);

        return back()->with('success', $successMessage);
    }
}
