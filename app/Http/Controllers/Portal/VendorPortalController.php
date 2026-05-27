<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Contact\Models\Contact;
use App\Domain\User\Models\User;
use App\Domain\WarrantyClaim\Actions\VendorApproveWarrantyClaim;
use App\Domain\WarrantyClaim\Actions\VendorRejectWarrantyClaim;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WarrantyClaim\Models\WarrantyClaimLineItem;
use App\Enums\Entity\PaymentTerms;
use App\Enums\WarrantyClaim\Status;
use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VendorPortalController extends Controller
{
    public function __construct(
        protected VendorApproveWarrantyClaim $vendorApproveWarrantyClaim,
        protected VendorRejectWarrantyClaim $vendorRejectWarrantyClaim,
        protected NotificationService $notifications,
    ) {}

    public function noManufacturerPortalAccess(): Response
    {
        return Inertia::render('VendorPortal/NoManufacturerPortalAccess');
    }

    public function index(Request $request): Response
    {
        $contact = $this->contact();

        $recent = $this->vendorPortalWarrantyClaimsQuery($contact)
            ->with(['vendor:id,display_name'])
            ->latest('updated_at')
            ->take(8)
            ->get();

        $counts = [
            'warrantyClaims' => $this->vendorPortalWarrantyClaimsQuery($contact)->count(),
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

        $claims = $this->vendorPortalWarrantyClaimsQuery($contact)
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
            ->with([
                'vendor' => fn ($q) => $q->select([
                    'id',
                    'display_name',
                    'assigned_user_id',
                    'payment_terms',
                    'primary_contact_id',
                ])->with(['assigned_user']),
                'workOrder:id,work_order_number',
                'lineItems' => fn ($q) => $q->orderBy('id')->with([
                    'workOrderServiceItem' => fn ($q2) => $q2->select(['id', 'display_name', 'description', 'work_order_id']),
                ]),
                'subsidiary:id,display_name',
                'location:id,display_name',
            ])
            ->firstOrFail();

        if ($claim->vendor !== null) {
            $claim->vendor->setAttribute(
                'payment_terms_label',
                $this->paymentTermsLabel($claim->vendor->getAttribute('payment_terms')),
            );
        }

        $status = $claim->status instanceof Status ? $claim->status : Status::tryFrom((string) $claim->getRawOriginal('status')) ?? Status::Draft;

        return Inertia::render('VendorPortal/WarrantyClaimShow', [
            'record' => $claim,
            'canRespond' => $status === Status::Submitted,
            'canEditLineFeedback' => $this->vendorMayEditLineFeedback($status),
            'statuses' => Status::options(),
        ]);
    }

    public function saveWarrantyClaimLineFeedback(Request $request, WarrantyClaim $warranty_claim): RedirectResponse
    {
        $this->assertClaimAccessible($warranty_claim);
        $contact = $this->contact();

        $claim = WarrantyClaim::query()
            ->whereKey($warranty_claim->getKey())
            ->with(['vendor:id,display_name,assigned_user_id', 'lineItems'])
            ->firstOrFail();

        $status = $claim->status instanceof Status ? $claim->status : Status::tryFrom((string) $claim->getRawOriginal('status')) ?? Status::Draft;

        if (! $this->vendorMayEditLineFeedback($status)) {
            return back()->with('error', 'Line feedback cannot be edited for this claim in its current status.');
        }

        if ($claim->lineItems->isEmpty()) {
            return back()->with('error', 'This claim has no line items.');
        }

        $validated = $request->validate([
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.id' => [
                'required',
                'integer',
                Rule::exists('warranty_claim_line_items', 'id')->where('warranty_claim_id', $claim->id),
            ],
            'line_items.*.notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $submittedIds = collect($validated['line_items'])->pluck('id')->map(static fn ($v) => (int) $v)->sort()->values();
        $expectedIds = $claim->lineItems->pluck('id')->map(static fn ($id) => (int) $id)->sort()->values();
        if ($submittedIds->count() !== $expectedIds->count() || $submittedIds->diff($expectedIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'line_items' => ['Submit feedback for every line item on this claim.'],
            ]);
        }

        $linesById = $claim->lineItems->keyBy('id');
        $notifyLines = [];

        DB::transaction(function () use ($validated, $linesById, &$notifyLines): void {
            foreach ($validated['line_items'] as $row) {
                $id = (int) $row['id'];
                $line = $linesById->get($id);
                if ($line === null) {
                    continue;
                }
                $old = trim((string) ($line->notes ?? ''));
                $new = trim((string) ($row['notes'] ?? ''));
                if ($old === $new) {
                    continue;
                }
                WarrantyClaimLineItem::query()
                    ->where('warranty_claim_id', $line->warranty_claim_id)
                    ->whereKey($id)
                    ->update(['notes' => $new === '' ? null : $new]);
                if ($new !== '') {
                    $notifyLines[] = [
                        'id' => $id,
                        'excerpt' => Str::limit($new, 160),
                    ];
                }
            }
        });

        if ($notifyLines !== [] && $claim->vendor?->assigned_user_id) {
            $assignee = User::query()->find((int) $claim->vendor->assigned_user_id);
            if ($assignee !== null) {
                $this->notifications->notifyWarrantyClaimVendorLineFeedback($assignee, $claim, $contact, $notifyLines);
            }
        }

        return redirect()
            ->route('vendor.portal.warranty-claims.show', $claim->getKey())
            ->with('success', 'Line feedback saved.');
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

    /**
     * Warranty claims visible in the vendor portal (draft is dealership-internal only).
     *
     * @return Builder<WarrantyClaim>
     */
    private function vendorPortalWarrantyClaimsQuery(Contact $contact): Builder
    {
        $vendorIds = $contact->vendorsWithPortalAccess()->pluck('vendors.id')->all();

        return WarrantyClaim::query()
            ->whereIn('vendor_id', $vendorIds)
            ->whereNot('status', Status::Draft->value);
    }

    private function assertClaimAccessible(WarrantyClaim $claim): void
    {
        Gate::forUser($this->contact())->authorize('vendorRespond', $claim);
    }

    private function vendorMayEditLineFeedback(Status $status): bool
    {
        return ! in_array($status, [Status::Draft, Status::Voided], true);
    }

    private function paymentTermsLabel(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        foreach (PaymentTerms::cases() as $case) {
            if ($case->id() === $id) {
                return $case->label();
            }
        }

        return null;
    }
}
