<?php

namespace App\Http\Controllers\Portal;

use App\Domain\AssetSpec\Support\SpecValueDisplayFormatter;
use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\CustomerAssetSpecSheetOptionSelection;
use App\Domain\Customer\Models\CustomerAssetSpecSheetShare;
use App\Domain\Document\Models\Document;
use App\Domain\Document\Support\PortalDocuments;
use App\Domain\DocumentRequest\Actions\FulfillDocumentRequest;
use App\Domain\DocumentRequest\Enums\DocumentRequestStatus;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Invoice\Support\InvoicePayOnline;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\ServiceTicket\Support\ServiceTicketPortalImages;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\User\Models\User;
use App\Enums\Estimate\EstimateStatus;
use App\Enums\Invoice\Status as InvoiceStatus;
use App\Enums\Payments\Terms;
use App\Enums\ServiceItem\BillingType;
use App\Enums\ServiceTicket\Status as ServiceTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\AssetOptionResolver;
use App\Services\NotificationService;
use App\Services\Payments\QuickBooksAccountingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                'documents' => PortalDocuments::countForCustomerProfile($customerProfile, $contact->id),
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

    public function invoiceShow(Request $request, Invoice $invoice, QuickBooksAccountingService $quickbooks): Response
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
            'quickbooks' => [
                'managed' => $invoice->isQuickbooksManaged(),
                'invoice_url' => $invoice->isQuickbooksManaged()
                    ? $quickbooks->customerInvoiceUrlForInvoice($invoice)
                    : null,
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
            'serviceTicketStatusOptions' => ServiceTicketStatus::options(),
        ]);
    }

    public function serviceTicketShow(Request $request, string $uuid): Response
    {
        ['customerId' => $customerId] = $this->portalContext();
        abort_if($customerId === null, 403);

        $ticket = ServiceTicket::query()
            ->where('uuid', $uuid)
            ->where('customer_id', $customerId)
            ->where('status', '!=', ServiceTicketStatus::Draft->id())
            ->with([
                'customer',
                'subsidiary',
                'location',
                'assetUnit' => function ($query) {
                    $query->with(['asset' => function ($q) {
                        $q->select(['id', 'display_name', 'year', 'make_id'])
                            ->with(['make' => function ($mq) {
                                $mq->select(['id', 'display_name']);
                            }]);
                    }]);
                },
                'serviceItems' => fn ($q) => $q->where('inactive', false)->orderBy('sort_order')->orderBy('id'),
                'images' => fn ($q) => $q,
            ])
            ->firstOrFail();

        $account = AccountSettings::getCurrent();
        $logoUrl = $account->logo_url ?? null;

        $recordArray = $ticket->toArray();
        $recordArray['created_at'] = $ticket->created_at?->toISOString();
        $recordArray['signed_at'] = $ticket->signed_at?->toISOString();
        $recordArray['declined_at'] = $ticket->declined_at?->toISOString();
        $recordArray['signature_url'] = $ticket->signature_url;

        $recordArray['service_items'] = $ticket->serviceItems->map(fn ($li) => [
            'id' => $li->id,
            'display_name' => $li->display_name,
            'description' => $li->description,
            'quantity' => (float) $li->quantity,
            'unit_price' => (float) $li->unit_price,
            'estimated_hours' => (float) ($li->estimated_hours ?? 0),
            'billable' => $li->billable,
            'warranty' => $li->warranty,
            'billing_type' => $li->billing_type,
        ])->values()->all();

        $recordArray['images'] = ServiceTicketPortalImages::forCustomer($ticket->images);

        return Inertia::render('Portal/ServiceTicketShow', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
            'enumOptions' => [
                'billing_type' => BillingType::options(),
            ],
        ]);
    }

    public function documents(Request $request): Response
    {
        ['contact' => $contact, 'customerProfile' => $customerProfile] = $this->portalContext();

        $paginator = PortalDocuments::paginateForCustomerProfile($customerProfile, $request, $contact->id);

        $pendingDocumentRequests = DocumentRequest::query()
            ->where('contact_id', $contact->id)
            ->where('status', DocumentRequestStatus::Pending)
            ->orderByDesc('sent_at')
            ->get()
            ->map(fn (DocumentRequest $row) => [
                'id' => $row->id,
                'title' => $row->title,
                'description' => $row->description,
                'sent_at' => $row->sent_at?->toIso8601String(),
            ]);

        $tab = $request->query('tab');
        $activeTab = in_array($tab, ['documents', 'requests'], true) ? $tab : 'documents';

        return Inertia::render('Portal/Documents', [
            'documents' => [
                'data' => PortalDocuments::mapForPortal($paginator),
                'links' => $paginator->linkCollection()->toArray(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'pendingDocumentRequests' => $pendingDocumentRequests,
            'activeTab' => $activeTab,
        ]);
    }

    public function downloadDocument(int $document)
    {
        ['customerProfile' => $customerProfile] = $this->portalContext();

        abort_if($customerProfile === null, 403);

        $record = Document::query()->findOrFail($document);
        $contact = Auth::guard('customer')->user();
        abort_unless(
            PortalDocuments::customerCanDownload($customerProfile, $record, $contact?->id),
            403,
        );

        if (! $record->file || ! Storage::disk('s3')->exists($record->file)) {
            abort(404);
        }

        return Storage::disk('s3')->download(
            $record->file,
            $record->display_name ?? 'document'
        );
    }

    public function fulfillDocumentRequest(
        Request $request,
        DocumentRequest $documentRequest,
        FulfillDocumentRequest $fulfillDocumentRequest,
    ) {
        /** @var Contact $contact */
        $contact = Auth::guard('customer')->user();

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,pdf,doc,docx,csv,txt,xlsx', 'max:51200'],
        ]);

        $result = $fulfillDocumentRequest(
            $documentRequest,
            $contact,
            $validated['file'],
        );

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Upload failed.');
        }

        return redirect()
            ->route('portal.documents', ['tab' => 'documents'])
            ->with('success', 'Document uploaded successfully. Thank you!');
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

        return Inertia::render('Portal/SpecSheets', [
            'shares' => $shares,
            'assignedUser' => $this->portalAssignedUserForCustomerProfile($customerProfile),
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

    public function storeSpecSheetOptionSelections(Request $request, string $uuid): RedirectResponse
    {
        ['customerProfile' => $customerProfile] = $this->portalContext();

        abort_if($customerProfile === null, 403);

        $validated = $request->validate([
            'selections' => ['nullable', 'array'],
            'selections.*.option_id' => ['required', 'integer'],
            'selections.*.option_value_id' => ['required', 'integer'],
        ]);

        $share = CustomerAssetSpecSheetShare::query()
            ->where('uuid', $uuid)
            ->where('customer_profile_id', $customerProfile->id)
            ->firstOrFail();

        $share->load(['asset', 'assetVariant']);
        $asset = $share->asset;
        abort_if($asset === null, 404);

        $variant = $share->assetVariant;
        $resolved = app(AssetOptionResolver::class)->resolve($asset, $variant)->keyBy('option_id');

        $selections = $validated['selections'] ?? [];

        $selectedByOption = [];
        foreach ($selections as $sel) {
            $oid = (int) $sel['option_id'];
            $vid = (int) $sel['option_value_id'];
            $selectedByOption[$oid][$vid] = $vid;
        }
        foreach ($selectedByOption as $oid => $vids) {
            $selectedByOption[$oid] = array_values($vids);
        }

        foreach ($resolved as $optionPayload) {
            $optionId = (int) $optionPayload['option_id'];
            $valueIds = $selectedByOption[$optionId] ?? [];

            if (($optionPayload['is_required'] ?? false) && $valueIds === []) {
                throw ValidationException::withMessages([
                    'selections' => 'Option "'.$optionPayload['name'].'" is required.',
                ]);
            }

            $allowMultiple = (bool) ($optionPayload['allow_multiple'] ?? false);
            if (! $allowMultiple && count($valueIds) > 1) {
                throw ValidationException::withMessages([
                    'selections' => 'Option "'.$optionPayload['name'].'" allows only one selection.',
                ]);
            }

            $min = $optionPayload['min_select'] ?? null;
            $max = $optionPayload['max_select'] ?? null;
            $count = count($valueIds);
            if ($min !== null && $count < $min) {
                throw ValidationException::withMessages([
                    'selections' => 'Option "'.$optionPayload['name'].'" requires at least '.$min.' selection(s).',
                ]);
            }
            if ($max !== null && $count > $max) {
                throw ValidationException::withMessages([
                    'selections' => 'Option "'.$optionPayload['name'].'" allows at most '.$max.' selection(s).',
                ]);
            }
        }

        foreach ($selections as $sel) {
            $oid = (int) $sel['option_id'];
            $vid = (int) $sel['option_value_id'];
            $optionPayload = $resolved->get($oid);
            if ($optionPayload === null) {
                throw ValidationException::withMessages([
                    'selections' => 'Invalid option selection.',
                ]);
            }
            $valueMeta = collect($optionPayload['values'] ?? [])->firstWhere('id', $vid);
            if ($valueMeta === null) {
                throw ValidationException::withMessages([
                    'selections' => 'Invalid option value.',
                ]);
            }
        }

        DB::transaction(function () use ($share, $selections, $resolved): void {
            CustomerAssetSpecSheetOptionSelection::query()
                ->where('customer_asset_spec_sheet_share_id', $share->id)
                ->delete();

            foreach ($selections as $sel) {
                $oid = (int) $sel['option_id'];
                $vid = (int) $sel['option_value_id'];
                $optionPayload = $resolved->get($oid);
                $valueMeta = collect($optionPayload['values'] ?? [])->firstWhere('id', $vid);

                CustomerAssetSpecSheetOptionSelection::query()->create([
                    'customer_asset_spec_sheet_share_id' => $share->id,
                    'option_id' => $oid,
                    'option_value_id' => $vid,
                    'option_name' => $optionPayload['name'],
                    'value_label' => $valueMeta['label'],
                    'cost' => $valueMeta['cost'],
                    'price' => $valueMeta['price'],
                ]);
            }
        });

        return redirect()->route('portal.specSheet.show', ['uuid' => $share->uuid])
            ->with('success', 'Your option selections have been saved.');
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
            'optionSelections',
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

        $assetOptions = app(AssetOptionResolver::class)->resolve($asset, $variant)->values()->all();

        $savedSelections = $share->optionSelections
            ->map(fn (CustomerAssetSpecSheetOptionSelection $s) => [
                'option_id' => $s->option_id,
                'option_value_id' => $s->option_value_id,
            ])
            ->values()
            ->all();

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
            'assetOptions' => $assetOptions,
            'savedSelections' => $savedSelections,
            'specSheetOptionsSaveUrl' => route('portal.specSheet.options.save', ['uuid' => $share->uuid]),
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

    private function estimatesForCustomer(?int $customerId): Builder
    {
        return Estimate::query()
            ->when(
                $customerId !== null,
                fn ($q) => $q->where('customer_id', $customerId),
                fn ($q) => $q->whereRaw('0 = 1'),
            )
            ->where('status', '!=', EstimateStatus::Draft->id());
    }

    private function serviceTicketsForCustomer(?int $customerId): Builder
    {
        return ServiceTicket::query()
            ->when(
                $customerId !== null,
                fn ($q) => $q->where('customer_id', $customerId),
                fn ($q) => $q->whereRaw('0 = 1'),
            )
            ->where('status', '!=', ServiceTicketStatus::Draft->id());
    }

    private function buildLineItems(Estimate $estimate): array
    {
        if (! $estimate->primaryVersion) {
            return [];
        }

        $estimate->loadMissing('selectedAssetOptions');

        $optionsByLineId = $estimate->selectedAssetOptions->groupBy('transaction_line_item_id');

        return $estimate->primaryVersion->lineItems->map(function ($li) use ($optionsByLineId) {
            $v = $li->assetVariant;
            $variantLabel = $v ? ($v->display_name ?: $v->name) : null;
            if ($variantLabel === null && $li->asset_variant_id) {
                $variantLabel = 'Variant #'.$li->asset_variant_id;
            }

            $selectedOptions = ($optionsByLineId->get($li->id) ?? collect())
                ->map(fn ($s) => [
                    'option_name' => $s->option_name,
                    'value_label' => $s->value_label,
                    'price' => (float) $s->price,
                ])
                ->values()
                ->all();

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
                'selected_options' => $selectedOptions,
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
