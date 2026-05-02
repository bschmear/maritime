<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Contact\Models\Contact;
use App\Domain\WarrantyClaim\Actions\VendorApproveWarrantyClaim;
use App\Domain\WarrantyClaim\Actions\VendorRejectWarrantyClaim;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Enums\WarrantyClaim\Status;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VendorPortalController extends Controller
{
    public function __construct(
        protected VendorApproveWarrantyClaim $vendorApproveWarrantyClaim,
        protected VendorRejectWarrantyClaim $vendorRejectWarrantyClaim,
    ) {}

    public function index(Request $request): Response
    {
        $contact = $this->contact();
        $vendorIds = $contact->vendors()->pluck('vendors.id')->all();

        $recent = WarrantyClaim::query()
            ->whereIn('vendor_id', $vendorIds)
            ->with(['vendor:id,display_name'])
            ->latest('updated_at')
            ->take(8)
            ->get();

        $counts = [
            'warrantyClaims' => WarrantyClaim::query()->whereIn('vendor_id', $vendorIds)->count(),
        ];

        return Inertia::render('VendorPortal/Overview', [
            'vendor' => $contact->only('id', 'display_name', 'first_name', 'last_name', 'email'),
            'recentWarrantyClaims' => $recent,
            'counts' => $counts,
            'statuses' => Status::options(),
        ]);
    }

    public function warrantyClaims(Request $request): Response
    {
        $contact = $this->contact();
        $vendorIds = $contact->vendors()->pluck('vendors.id')->all();

        $claims = WarrantyClaim::query()
            ->whereIn('vendor_id', $vendorIds)
            ->with(['vendor:id,display_name'])
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('VendorPortal/WarrantyClaims', [
            'claims' => $claims,
            'statuses' => Status::options(),
        ]);
    }

    public function warrantyClaimShow(Request $request, WarrantyClaim $warranty_claim): Response
    {
        $this->assertClaimAccessible($warranty_claim);

        $claim = WarrantyClaim::query()
            ->whereKey($warranty_claim->getKey())
            ->with(['vendor:id,display_name', 'workOrder:id,display_name,work_order_number', 'lineItems', 'subsidiary:id,display_name', 'location:id,display_name'])
            ->firstOrFail();

        $status = $claim->status instanceof Status ? $claim->status : Status::tryFrom((string) $claim->getRawOriginal('status')) ?? Status::Draft;

        return Inertia::render('VendorPortal/WarrantyClaimShow', [
            'record' => $claim,
            'canRespond' => $status === Status::Submitted,
            'statuses' => Status::options(),
        ]);
    }

    public function approveWarrantyClaim(Request $request, WarrantyClaim $warranty_claim): RedirectResponse
    {
        $this->assertClaimAccessible($warranty_claim);
        $contact = $this->contact();

        $result = ($this->vendorApproveWarrantyClaim)($warranty_claim, (int) $contact->id);

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Unable to approve this claim.');
        }

        return redirect()
            ->route('vendor.portal.warranty-claims.show', $warranty_claim->getKey())
            ->with('success', 'Claim approved.');
    }

    public function rejectWarrantyClaim(Request $request, WarrantyClaim $warranty_claim): RedirectResponse
    {
        $this->assertClaimAccessible($warranty_claim);
        $contact = $this->contact();

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
            'vendor_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $result = ($this->vendorRejectWarrantyClaim)($warranty_claim, (int) $contact->id, $validated);

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Unable to reject this claim.');
        }

        return redirect()
            ->route('vendor.portal.warranty-claims.show', $warranty_claim->getKey())
            ->with('success', 'Claim rejected.');
    }

    private function contact(): Contact
    {
        /** @var Contact $c */
        $c = Auth::guard('vendor')->user();

        return $c;
    }

    private function assertClaimAccessible(WarrantyClaim $claim): void
    {
        Gate::forUser($this->contact())->authorize('vendorRespond', $claim);
    }
}
