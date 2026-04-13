<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Estimate\Models\Estimate;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Enums\Estimate\EstimateStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPortalController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $customer = Auth::guard('customer')->user();

        $estimates = Estimate::where('customer_id', $customer->id)
            ->where('status', '!=', EstimateStatus::Draft->id())
            ->latest()
            ->take(5)
            ->get();

        $invoices = Invoice::where('contact_id', $customer->contact_id)
            ->latest()
            ->take(5)
            ->get();

        $serviceTickets = ServiceTicket::where('customer_id', $customer->id)
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Portal/Overview', [
            'customer' => $customer->only('id', 'display_name', 'first_name', 'last_name', 'email'),
            'recentEstimates' => $estimates,
            'recentInvoices' => $invoices,
            'recentServiceTickets' => $serviceTickets,
            'estimateStatuses' => EstimateStatus::options(),
            'counts' => [
                'estimates' => Estimate::where('customer_id', $customer->id)
                    ->where('status', '!=', EstimateStatus::Draft->id())->count(),
                'invoices' => Invoice::where('contact_id', $customer->contact_id)->count(),
                'serviceTickets' => ServiceTicket::where('customer_id', $customer->id)->count(),
                'documents' => $customer->documents()->count(),
            ],
        ]);
    }

    public function estimates(Request $request): Response
    {
        $customer = Auth::guard('customer')->user();

        $estimates = Estimate::where('customer_id', $customer->id)
            ->where('status', '!=', EstimateStatus::Draft->id())
            ->with(['primaryVersion:id,estimate_id,subtotal,tax,total'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Portal/Estimates', [
            'estimates' => $estimates,
            'statuses' => EstimateStatus::options(),
        ]);
    }

    public function estimateShow(Request $request, int $id): Response
    {
        $customer = Auth::guard('customer')->user();

        $estimate = Estimate::where('id', $id)
            ->where('customer_id', $customer->id)
            ->with([
                'primaryVersion.lineItems' => fn ($q) => $q->with([
                    'addons',
                    'assetVariant',
                ]),
                'user',
                'customer',
                'opportunity',
            ])
            ->firstOrFail();

        $account = AccountSettings::getCurrent();

        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;

        $reviewUrl = $domain
            ? "https://{$domain}/estimates/{$estimate->uuid}/review"
            : null;

        $recordArray = $estimate->toArray();
        $recordArray['signature_url'] = $estimate->signature_url;
        $recordArray['signed_at'] = $estimate->signed_at?->toISOString();
        $recordArray['declined_at'] = $estimate->declined_at?->toISOString();

        $recordArray['line_items'] = $this->buildLineItems($estimate);
        $recordArray['subtotal'] = (float) ($estimate->primaryVersion?->subtotal ?? 0);
        $recordArray['tax'] = (float) ($estimate->primaryVersion?->tax ?? 0);
        $recordArray['total'] = (float) ($estimate->primaryVersion?->total ?? 0);

        return Inertia::render('Portal/EstimateShow', [
            'estimate' => $recordArray,
            'account' => $account,
            'logoUrl' => $account->logo_url ?? null,
            'reviewUrl' => $reviewUrl,
            'approveUrl' => url("/portal/estimates/{$estimate->id}/approve"),
            'declineUrl' => url("/portal/estimates/{$estimate->id}/decline"),
            'statuses' => EstimateStatus::options(),
        ]);
    }

    public function approveEstimate(Request $request, int $id)
    {
        $customer = Auth::guard('customer')->user();

        $estimate = Estimate::where('id', $id)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', EstimateStatus::Approved->id())
            ->firstOrFail();

        if ($estimate->approved_at) {
            return back();
        }

        $request->validate([
            'consent' => 'required|accepted',
            'approval_note' => 'nullable|string|max:1000',
        ]);

        $account = AccountSettings::getCurrent();

        $estimate->update([
            'status' => EstimateStatus::Approved->id(),
            'approved_at' => now(),
            'approval_note' => $request->approval_note,
        ]);

        $estimate->refresh();

        $this->notifications->notifyEstimateApproved($estimate, $account);

        return back();
    }

    public function declineEstimate(Request $request, int $id)
    {
        $customer = Auth::guard('customer')->user();

        $estimate = Estimate::where('id', $id)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', EstimateStatus::Declined->id())
            ->firstOrFail();

        if ($estimate->declined_at) {
            return back();
        }

        $request->validate([
            'decline_reason' => 'required|string|max:1000',
        ]);

        $estimate->update([
            'status' => EstimateStatus::Declined->id(),
            'declined_at' => now(),
            'decline_reason' => $request->decline_reason,
        ]);

        $estimate->refresh();

        $this->notifications->notifyEstimateDeclined($estimate, AccountSettings::getCurrent());

        return back();
    }

    public function invoices(Request $request): Response
    {
        $customer = Auth::guard('customer')->user();

        $invoices = Invoice::where('contact_id', $customer->contact_id)
            ->latest()
            ->paginate(15);

        return Inertia::render('Portal/Invoices', [
            'invoices' => $invoices,
        ]);
    }

    public function serviceTickets(Request $request): Response
    {
        $customer = Auth::guard('customer')->user();

        $serviceTickets = ServiceTicket::where('customer_id', $customer->id)
            ->latest()
            ->paginate(15);

        return Inertia::render('Portal/ServiceTickets', [
            'serviceTickets' => $serviceTickets,
        ]);
    }

    public function documents(Request $request): Response
    {
        $customer = Auth::guard('customer')->user();

        $documents = $customer->documents()
            ->latest()
            ->paginate(15);

        return Inertia::render('Portal/Documents', [
            'documents' => $documents,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function buildLineItems(Estimate $estimate): array
    {
        if (! $estimate->primaryVersion) {
            return [];
        }

        return $estimate->primaryVersion->lineItems->map(function ($li) {
            $v = $li->assetVariant;
            $variantLabel = $v ? ($v->display_name ?: $v->name) : null;
            if ($variantLabel === null && $li->asset_variant_id) {
                $variantLabel = 'Variant #'.$li->asset_variant_id;
            }

            return [
                'id' => $li->id,
                'name' => $li->name,
                'description' => $li->description,
                'itemable_type' => $li->itemable_type,
                'asset_variant_id' => $li->asset_variant_id,
                'variant_display_name' => $variantLabel,
                'quantity' => (float) $li->quantity,
                'unit_price' => (float) $li->unit_price,
                'discount' => (float) ($li->discount ?? 0),
                'line_total' => (float) $li->line_total,
                'addons' => $li->addons->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'price' => (float) $a->price,
                    'quantity' => (int) $a->quantity,
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
