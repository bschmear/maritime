<?php

namespace App\Http\Controllers\Portal;

use App\Domain\AssetSpec\Support\SpecValueDisplayFormatter;
use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\CustomerAssetSpecSheetShare;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Invoice\Support\InvoicePayOnline;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\User\Models\User;
use App\Enums\Estimate\EstimateStatus;
use App\Enums\Invoice\Status as InvoiceStatus;
use App\Enums\Payments\Terms;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPortalController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        ['contact' => $contact, 'customerId' => $customerId, 'customerProfile' => $customerProfile] = $this->portalContext();

        $estimates = $this->estimatesForCustomer($customerId)
            ->latest()
            ->take(5)
            ->get();

        $invoices = Invoice::where('contact_id', $contact->id)
            ->whereIn('status', InvoiceStatus::customerPortalValues())
            ->latest()
            ->take(5)
            ->get();

        $serviceTickets = $this->serviceTicketsForCustomer($customerId)
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Portal/Overview', [
            'customer' => $contact->only('id', 'display_name', 'first_name', 'last_name', 'email'),
            'recentEstimates' => $estimates,
            'recentInvoices' => $invoices,
            'recentServiceTickets' => $serviceTickets,
            'estimateStatuses' => EstimateStatus::options(),
            'counts' => [
                'estimates' => $this->estimatesForCustomer($customerId)->count(),
                'invoices' => Invoice::where('contact_id', $contact->id)
                    ->whereIn('status', InvoiceStatus::customerPortalValues())
                    ->count(),
                'serviceTickets' => $this->serviceTicketsForCustomer($customerId)->count(),
                'documents' => $customerProfile ? $customerProfile->documents()->count() : 0,
                'specSheets' => $customerProfile
                    ? CustomerAssetSpecSheetShare::query()->where('customer_profile_id', $customerProfile->id)->count()
                    : 0,
            ],
        ]);
    }

    public function estimates(Request $request): Response
    {
        ['customerId' => $customerId] = $this->portalContext();

        $estimates = $this->estimatesForCustomer($customerId)
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
        ['customerId' => $customerId] = $this->portalContext();

        $estimate = Estimate::where('id', $id)
            ->when(
                $customerId !== null,
                fn ($q) => $q->where('customer_id', $customerId),
                fn ($q) => $q->whereRaw('0 = 1'),
            )
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
        ['customerId' => $customerId] = $this->portalContext();

        $estimate = Estimate::where('id', $id)
            ->when(
                $customerId !== null,
                fn ($q) => $q->where('customer_id', $customerId),
                fn ($q) => $q->whereRaw('0 = 1'),
            )
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
        ['customerId' => $customerId] = $this->portalContext();

        $estimate = Estimate::where('id', $id)
            ->when(
                $customerId !== null,
                fn ($q) => $q->where('customer_id', $customerId),
                fn ($q) => $q->whereRaw('0 = 1'),
            )
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
        $contact = $this->portalContext()['contact'];

        $invoices = Invoice::where('contact_id', $contact->id)
            ->whereIn('status', InvoiceStatus::customerPortalValues())
            ->latest()
            ->paginate(15);

        return Inertia::render('Portal/Invoices', [
            'invoices' => $invoices,
        ]);
    }

    public function invoiceShow(Request $request, Invoice $invoice): Response
    {
        $contact = $this->portalContext()['contact'];

        abort_if((int) $invoice->contact_id !== (int) $contact->id, 403);

        abort_unless(
            in_array($invoice->status, InvoiceStatus::customerPortalValues(), true),
            403,
            'This invoice is not available in the portal.',
        );

        $invoice->load(Invoice::documentEagerLoads());
        $invoice->markAsViewed();
        $invoice = $invoice->fresh(Invoice::documentEagerLoads()) ?? $invoice;

        $account = AccountSettings::getCurrent();
        $canPayOnline = InvoicePayOnline::canPayOnline($invoice);

        return Inertia::render('Portal/InvoiceShow', [
            'record' => $invoice,
            'account' => $account,
            'logoUrl' => $account->logo_url ?? null,
            'enumOptions' => [
                Terms::class => Terms::options(),
                InvoiceStatus::class => InvoiceStatus::options(),
            ],
            'canPayOnline' => $canPayOnline,
            'paymentConstraints' => [
                'allow_partial_payment' => (bool) $invoice->allow_partial_payment,
                'minimum_partial_amount' => $invoice->minimum_partial_amount !== null
                    ? (float) $invoice->minimum_partial_amount
                    : null,
                'amount_due' => (float) $invoice->amount_due,
                'amount_paid' => (float) $invoice->amount_paid,
                'surcharge_percent' => (float) ($invoice->surcharge_percent ?? 0),
            ],
        ]);
    }

    public function serviceTickets(Request $request): Response
    {
        ['customerId' => $customerId] = $this->portalContext();

        $serviceTickets = $this->serviceTicketsForCustomer($customerId)
            ->latest()
            ->paginate(15);

        return Inertia::render('Portal/ServiceTickets', [
            'serviceTickets' => $serviceTickets,
        ]);
    }

    public function documents(Request $request): Response
    {
        ['customerProfile' => $customerProfile] = $this->portalContext();

        $documents = $customerProfile
            ? $customerProfile->documents()->latest()->paginate(15)
            : new LengthAwarePaginator([], 0, 15, 1, [
                'path' => $request->url(),
                'pageName' => 'page',
            ]);

        return Inertia::render('Portal/Documents', [
            'documents' => $documents,
        ]);
    }

    public function specSheets(Request $request): Response
    {
        ['customerProfile' => $customerProfile] = $this->portalContext();

        abort_if($customerProfile === null, 403);

        $latestIdsSub = CustomerAssetSpecSheetShare::query()
            ->where('customer_profile_id', $customerProfile->id)
            ->groupBy('asset_id', 'asset_variant_id')
            ->selectRaw('MAX(id) as id');

        $shares = CustomerAssetSpecSheetShare::query()
            ->where('customer_profile_id', $customerProfile->id)
            ->whereIn('id', $latestIdsSub)
            ->with(['asset.make', 'assetVariant'])
            ->latest('sent_at')
            ->paginate(15);

        $account = AccountSettings::getCurrent();

        return Inertia::render('Portal/SpecSheets', [
            'shares' => $shares,
            'assignedUser' => $this->portalAssignedUserForCustomerProfile($customerProfile),
            'timezone' => $account->timezone ?: (string) config('app.timezone', 'UTC'),
        ]);
    }

    public function specSheetShow(Request $request, string $uuid): Response
    {
        ['customerProfile' => $customerProfile] = $this->portalContext();

        abort_if($customerProfile === null, 403);

        $share = CustomerAssetSpecSheetShare::query()
            ->where('uuid', $uuid)
            ->where('customer_profile_id', $customerProfile->id)
            ->firstOrFail();

        return Inertia::render('Portal/SpecSheetShow', $this->specSheetInertiaProps($share, $customerProfile));
    }

    /**
     * Contact info for the tenant user assigned to this customer profile (portal spec sheets).
     *
     * @return array{name: string|null, email: string|null, phone: string|null}|null
     */
    private function portalAssignedUserForCustomerProfile(?Customer $customerProfile): ?array
    {
        if ($customerProfile === null || ! $customerProfile->assigned_user_id) {
            return null;
        }

        $user = User::query()->find((int) $customerProfile->assigned_user_id);
        if ($user === null) {
            return null;
        }

        $name = $user->display_name
            ?: trim(implode(' ', array_filter([(string) $user->first_name, (string) $user->last_name])))
            ?: null;

        $phone = $user->office_phone ?: $user->mobile_phone ?: null;
        $email = $user->email ?: null;

        if ($name === null && $email === null && $phone === null) {
            return null;
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function specSheetInertiaProps(CustomerAssetSpecSheetShare $share, Customer $customerProfile): array
    {
        $share->load([
            'asset.make',
            'asset.images',
            'asset.specValues.definition',
            'assetVariant.specValues.definition',
            'assetVariant.asset.make',
        ]);

        $account = AccountSettings::getCurrent();
        $asset = $share->asset;
        abort_if($asset === null, 404);

        $variant = $share->assetVariant;
        $primaryImageUrl = $asset->images->sortByDesc('is_primary')->first()?->url;

        if ($variant !== null) {
            $headline = $asset->display_name ?? 'Asset';
            $subhead = $variant->display_name ?: $variant->name ?: 'Variant';
            $description = $variant->resolvedDescription();
            $specRows = SpecValueDisplayFormatter::labeledRowsFromVariant($variant);
        } else {
            $headline = $asset->display_name ?? 'Asset';
            $subhead = null;
            $description = $asset->description;
            $specRows = SpecValueDisplayFormatter::labeledRowsFromAsset($asset);
        }

        $contact = Auth::guard('customer')->user();
        $subsidiary = null;
        if ($contact !== null) {
            $contact->loadMissing('customer.subsidiary');
            $subsidiary = $contact->customer?->subsidiary;
        }
        $fallbackSubsidiary = $subsidiary ?? Subsidiary::query()->orderBy('id')->first();

        $settings = is_array($account->settings) ? $account->settings : [];
        $businessName = trim((string) ($settings['business_name'] ?? ''));

        $dealerHeader = [
            'display_name' => $fallbackSubsidiary?->display_name ?: ($businessName !== '' ? $businessName : ($account->name ?? 'Dealer')),
            'logo_url' => $fallbackSubsidiary?->logo_url ?? $account->logo_url,
            // Hide location/contact lines in spec sheet header.
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'phone' => null,
            'email' => null,
        ];

        return [
            'shareUuid' => $share->uuid,
            'documentRef' => strtoupper(Str::substr((string) $share->uuid, 0, 8)),
            'headline' => $headline,
            'subhead' => $subhead,
            'description' => $description,
            'makeName' => $asset->make?->display_name,
            'year' => $asset->year,
            'specRows' => $specRows,
            'primaryImageUrl' => $primaryImageUrl,
            'account' => $account,
            'logoUrl' => $account->logo_url ?? null,
            'dealerHeader' => $dealerHeader,
            'sentAt' => $share->sent_at?->toISOString(),
            'appName' => (string) config('app.name', 'Maritime'),
            'termsUrl' => rtrim((string) config('app.url', ''), '/').'/terms',
            'assignedUser' => $this->portalAssignedUserForCustomerProfile($customerProfile),
        ];
    }

    /**
     * @return array{contact: Contact, customerProfile: ?Customer, customerId: ?int}
     */
    private function portalContext(): array
    {
        /** @var Contact $contact */
        $contact = Auth::guard('customer')->user();
        $contact->loadMissing('customer');
        $customerProfile = $contact->customer;

        return [
            'contact' => $contact,
            'customerProfile' => $customerProfile,
            'customerId' => $customerProfile?->id,
        ];
    }

    private function estimatesForCustomer(?int $customerId): \Illuminate\Database\Eloquent\Builder
    {
        return Estimate::query()
            ->when(
                $customerId !== null,
                fn ($q) => $q->where('customer_id', $customerId),
                fn ($q) => $q->whereRaw('0 = 1'),
            )
            ->where('status', '!=', EstimateStatus::Draft->id());
    }

    private function serviceTicketsForCustomer(?int $customerId): \Illuminate\Database\Eloquent\Builder
    {
        return ServiceTicket::query()
            ->when(
                $customerId !== null,
                fn ($q) => $q->where('customer_id', $customerId),
                fn ($q) => $q->whereRaw('0 = 1'),
            );
    }

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
