<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Delivery\Models\Delivery;
use App\Domain\Delivery\Support\DeliveryApproverResolver;
use App\Domain\Location\Actions\UpdateLocation;
use App\Domain\Location\Models\Location;
use App\Models\AccountSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AccountDeliveryManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        abort_unless(tenant_has_permission('location.edit'), 403);

        $showInactive = $request->boolean('show_inactive');

        $locations = Location::query()
            ->with(['managerUser', 'deliveryApprover'])
            ->when(! $showInactive, fn ($q) => $q->where('inactive', false))
            ->orderBy('inactive')
            ->orderBy('display_name')
            ->get()
            ->map(function (Location $location) {
                $effective = DeliveryApproverResolver::forLocation($location);
                $pendingCount = Delivery::query()
                    ->where('status', 'requested')
                    ->where('location_id', $location->id)
                    ->count();

                return [
                    'id' => $location->id,
                    'display_name' => $location->display_name,
                    'inactive' => (bool) $location->inactive,
                    'manager_user_id' => $location->manager_user_id,
                    'manager' => $location->managerUser ? [
                        'id' => $location->managerUser->id,
                        'display_name' => $location->managerUser->display_name,
                    ] : null,
                    'delivery_approver_user_id' => $location->delivery_approver_user_id,
                    'delivery_approver' => $location->deliveryApprover ? [
                        'id' => $location->deliveryApprover->id,
                        'display_name' => $location->deliveryApprover->display_name,
                    ] : null,
                    'effective_approver' => $effective ? [
                        'id' => $effective->id,
                        'display_name' => $effective->display_name,
                        'uses_manager_fallback' => $location->delivery_approver_user_id === null
                            && $location->manager_user_id !== null
                            && (int) $location->manager_user_id === (int) $effective->id,
                    ] : null,
                    'pending_request_count' => $pendingCount,
                ];
            })
            ->values();

        return Inertia::render('Tenant/Account/DeliveryManagement', [
            'locations' => $locations,
            'showInactive' => $showInactive,
            'account' => AccountSettings::getCurrent()->only(['id', 'name']),
        ]);
    }

    public function update(Request $request, UpdateLocation $updateLocation): RedirectResponse
    {
        abort_unless(tenant_has_permission('location.edit'), 403);

        $validated = $request->validate([
            'approvers' => 'required|array',
            'approvers.*.location_id' => 'required|integer|exists:locations,id',
            'approvers.*.delivery_approver_user_id' => 'nullable|integer|exists:users,id',
        ]);

        foreach ($validated['approvers'] as $row) {
            $updateLocation((int) $row['location_id'], [
                'delivery_approver_user_id' => $row['delivery_approver_user_id'] ?? null,
            ]);
        }

        return back()->with('success', 'Delivery approvers updated.');
    }
}
