<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\AddOn\Models\AddOn;
use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Services\EstimateSelectedOptionSync;
use App\Domain\AssetSpec\Support\SpecValueDisplayFormatter;
use App\Domain\AssetVariant\Models\AssetVariant;
use App\Domain\ConsignmentPolicy\Models\ConsignmentPolicy;
use App\Domain\Contract\Models\Contract;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Estimate\Models\EstimateCustomerOptionSignoff;
use App\Domain\Estimate\Support\EstimateSyncPayloadFromVersion;
use App\Domain\Estimate\Support\RecalculateEstimateVersionTotals;
use App\Domain\FeatureRequest\Models\FeatureRequestInvite;
use App\Domain\Invoice\Actions\FulfillPublicInvoiceCheckoutSession;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Invoice\Support\InvoicePayOnline;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Models\OpportunityAssetAddon;
use App\Domain\Opportunity\Models\OpportunityFeatureRequest;
use App\Domain\Payment\Models\PaymentConfiguration;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Enums\Contract\ContractStatus;
use App\Enums\Deliveries\Status as DeliveryStatus;
use App\Enums\Estimate\EstimateStatus;
use App\Enums\Invoice\Status as InvoiceStatus;
use App\Enums\Payments\Terms;
use App\Enums\WarrantyClaim\LineItemCostType;
use App\Enums\WarrantyClaim\Status as WarrantyClaimStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountSettings;
use App\Services\AssetOptionResolver;
use App\Services\NotificationService;
use App\Services\Payments\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Printable specification sheet for an asset variant (UUID link, no login).
     */
    public function publicVariantSpecSheet(Request $request, string $publicUuid): Response
    {
        $variant = AssetVariant::query()
            ->where('public_uuid', $publicUuid)
            ->firstOrFail();

        $variant->load([
            'asset' => fn ($q) => $q->with(['make', 'images']),
            'specValues.definition',
        ]);

        $asset = $variant->asset;
        abort_if($asset === null, 404);

        $account = AccountSettings::getCurrent();
        $primaryImageUrl = $asset->images->sortByDesc('is_primary')->first()?->url;

        $headline = $asset->display_name ?? 'Asset';
        $subhead = $variant->display_name ?: $variant->name ?: 'Variant';
        $description = $variant->resolvedDescription();
        $specRows = SpecValueDisplayFormatter::labeledRowsFromVariant($variant);

        $settings = is_array($account->settings) ? $account->settings : [];
        $businessName = trim((string) ($settings['business_name'] ?? ''));
        $subsidiary = Subsidiary::query()->orderBy('id')->first();

        $dealerHeader = [
            'display_name' => $subsidiary?->display_name ?: ($businessName !== '' ? $businessName : ($account->name ?? 'Dealer')),
            'logo_url' => $subsidiary?->logo_url ?? $account->logo_url,
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'phone' => null,
            'email' => null,
        ];

        return Inertia::render('Tenant/Public/PublicVariantSpecSheet', [
            'documentRef' => strtoupper(Str::substr((string) $variant->public_uuid, 0, 8)),
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
            'sentAt' => null,
            'appName' => (string) config('app.name', 'Maritime'),
            'termsUrl' => rtrim((string) config('app.url', ''), '/').'/terms',
        ]);
    }

    public function review(Request $request, $uuid)
    {
        $ticket = ServiceTicket::where('uuid', $uuid)
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
        // Match public invoice branding (account logo only; not subsidiary-specific).
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

        $recordArray['images'] = $ticket->images->map(fn ($img) => [
            'id' => $img->id,
            'display_name' => $img->display_name,
            'url' => $img->url,
            'is_primary' => (bool) ($img->pivot?->is_primary ?? $img->is_primary),
        ])->values()->all();

        $enumOptions = [
            'billing_type' => \App\Enums\ServiceItem\BillingType::options(),
        ];

        return Inertia::render('Tenant/Public/ServiceTicketReview', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function approve(Request $request, $uuid)
    {
        $ticket = ServiceTicket::where('uuid', $uuid)->firstOrFail();

        if ($ticket->approved) {
            return back();
        }

        $request->validate([
            'signature_method' => 'required|in:draw,type',
            'signature_data' => 'required|string',
            'signed_name' => 'required|string|max:255',
            'consent' => 'required|accepted',
        ]);

        $account = AccountSettings::getCurrent();

        $items = $ticket->serviceItems()->where('inactive', false)->get();
        $itemsSnapshot = $items->map(fn ($item) => [
            'id' => $item->id,
            'display_name' => $item->display_name,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'billing_type' => $item->billing_type,
            'estimated_hours' => (string) $item->estimated_hours,
        ])->toArray();

        $approvalHash = hash('sha256', json_encode([
            'ticket_id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'estimated_total' => (string) $ticket->estimated_total,
            'ack_text' => $account->service_ticket_ack_text,
            'items' => $itemsSnapshot,
            'timestamp' => now()->toISOString(),
            'ip' => $request->ip(),
        ]));

        $signatureMethod = $request->signature_method === 'draw' ? 1 : 5;
        $signatureFile = null;
        if ($request->signature_method === 'draw') {
            $signatureFile = $this->storeSignatureImage($request->signature_data, $ticket->uuid);
        }

        $ticket->update([
            'approved' => true,
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
            'signed_user_agent' => $request->userAgent(),
            'signed_name' => $request->signed_name,
            'customer_signature' => $request->signature_data,
            'signature_method' => $signatureMethod,
            'signature_hash' => $approvalHash,
            'signature_file' => $signatureFile,
        ]);

        $ticket->refresh();

        $this->notifications->sendServiceTicketCustomerConfirmation($ticket, $account);
        $this->notifications->notifyServiceTicketApproved($ticket, $account);

        return back();
    }

    public function decline(Request $request, $uuid)
    {
        $ticket = ServiceTicket::where('uuid', $uuid)->firstOrFail();

        if ($ticket->approved || $ticket->declined_at) {
            return back();
        }

        $request->validate([
            'decline_reason' => 'nullable|string|max:1000',
        ]);

        $ticket->update([
            'declined_at' => now(),
            'decline_reason' => $request->decline_reason,
        ]);

        return back();
    }

    public function reviewWarrantyClaim(Request $request, string $uuid): Response
    {
        $claim = WarrantyClaim::query()
            ->where('uuid', $uuid)
            ->with([
                'vendor' => fn ($q) => $q->select(['id', 'display_name']),
                'workOrder' => fn ($q) => $q->select(['id', 'display_name', 'work_order_number']),
                'subsidiary' => fn ($q) => $q->select(['id', 'display_name']),
                'location' => fn ($q) => $q->select(['id', 'display_name']),
                'lineItems' => fn ($q) => $q->orderBy('id')->with([
                    'workOrderServiceItem' => fn ($q2) => $q2->select(['id', 'display_name', 'description', 'work_order_id']),
                ]),
                'images' => fn ($q) => $q,
                'documents',
            ])
            ->firstOrFail();

        $account = AccountSettings::getCurrent();
        $logoUrl = $account->logo_url ?? null;

        $recordArray = $claim->toArray();
        $recordArray['status'] = $claim->status instanceof WarrantyClaimStatus
            ? $claim->status->value
            : (string) $claim->getRawOriginal('status');
        $recordArray['created_at'] = $claim->created_at?->toISOString();
        $recordArray['submitted_at'] = $claim->submitted_at?->toISOString();
        $recordArray['approved_at'] = $claim->approved_at?->toISOString();

        $recordArray['line_items'] = $claim->lineItems->map(fn ($li) => [
            'id' => $li->id,
            'work_order_service_item_id' => $li->work_order_service_item_id,
            'description' => $li->description,
            'cost_type' => $li->cost_type instanceof \BackedEnum ? $li->cost_type->value : (string) $li->cost_type,
            'quantity' => (int) $li->quantity,
            'cost' => (float) $li->cost,
            'line_total_cost' => $li->line_total_cost,
            'notes' => $li->notes,
            'work_order_service_item' => $li->workOrderServiceItem
                ? [
                    'id' => $li->workOrderServiceItem->id,
                    'display_name' => $li->workOrderServiceItem->display_name,
                    'description' => $li->workOrderServiceItem->description,
                ]
                : null,
        ])->values()->all();

        $recordArray['images'] = $claim->images->map(fn ($img) => [
            'id' => $img->id,
            'display_name' => $img->display_name,
            'url' => $img->url,
            'is_primary' => (bool) ($img->pivot?->is_primary ?? false),
        ])->values()->all();

        $recordArray['documents'] = $claim->documents->map(fn ($doc) => [
            'id' => $doc->id,
            'display_name' => $doc->display_name,
            'file_extension' => $doc->file_extension,
        ])->values()->all();

        $enumOptions = [
            'cost_type' => LineItemCostType::options(),
            'status' => WarrantyClaimStatus::options(),
        ];

        return Inertia::render('Tenant/Public/WarrantyClaimReview', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
            'enumOptions' => $enumOptions,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Estimate Review / Approve / Decline
    // ─────────────────────────────────────────────────────────────────────────

    public function reviewEstimate(Request $request, string $uuid)
    {
        $estimate = Estimate::where('uuid', $uuid)
            ->with([
                'customer',
                'user',
                'opportunity',
                'selectedAssetOptions',
                'primaryVersion.lineItems' => fn ($q) => $q->with([
                    'addons',
                    'assetVariant',
                ]),
            ])
            ->firstOrFail();

        $account = AccountSettings::getCurrent();
        $logoUrl = $account->logo_url ?? null;

        $recordArray = $estimate->toArray();
        $recordArray['approved_at'] = $estimate->approved_at?->toISOString();
        $recordArray['approval_note'] = $estimate->approval_note;
        $recordArray['declined_at'] = $estimate->declined_at?->toISOString();
        $recordArray['decline_reason'] = $estimate->decline_reason;
        $recordArray['issue_date'] = $estimate->issue_date?->toISOString();
        $recordArray['expiration_date'] = $estimate->expiration_date?->toISOString();

        $recordArray['line_items'] = $this->buildLineItems($estimate);
        $recordArray['subtotal'] = (float) ($estimate->primaryVersion?->subtotal ?? 0);
        $recordArray['tax'] = (float) ($estimate->primaryVersion?->tax ?? 0);
        $recordArray['total'] = (float) ($estimate->primaryVersion?->total ?? 0);
        $recordArray['tax_rate'] = (float) ($estimate->tax_rate ?? $estimate->primaryVersion?->tax_rate ?? 0);

        return Inertia::render('Tenant/Public/EstimateReview', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
        ]);
    }

    public function approveEstimate(Request $request, string $uuid)
    {
        $estimate = Estimate::where('uuid', $uuid)->firstOrFail();

        if ($estimate->status == EstimateStatus::Approved->id() || $estimate->approved_at) {
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
            'signed_at' => now(),
            'approval_note' => $request->approval_note,
        ]);

        $estimate->refresh();

        $this->notifications->notifyEstimateApproved($estimate, $account);

        return back();
    }

    public function declineEstimate(Request $request, string $uuid)
    {
        $estimate = Estimate::where('uuid', $uuid)->firstOrFail();

        if ($estimate->status == EstimateStatus::Declined->id() || $estimate->declined_at) {
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

    public function boatOptionsEstimate(Request $request, string $uuid, int $line)
    {
        $estimate = Estimate::where('uuid', $uuid)
            ->with([
                'customer',
                'primaryVersion.lineItems',
            ])
            ->firstOrFail();

        $version = $estimate->primaryVersion;
        if ($version === null) {
            abort(404);
        }

        $lineItem = $version->lineItems->firstWhere('position', $line);
        if ($lineItem === null || ($lineItem->itemable_type ?? '') !== Asset::class) {
            abort(404);
        }

        if (($lineItem->asset_options_fill_mode ?? 'staff') !== 'customer') {
            abort(403, 'Boat options are not open for customer selection on this line.');
        }

        $account = AccountSettings::getCurrent();
        $logoUrl = $account->logo_url ?? null;

        $asset = Asset::query()->with('make:id,display_name')->find((int) $lineItem->itemable_id);
        if ($asset === null) {
            abort(404);
        }

        $variantId = $lineItem->asset_variant_id ? (int) $lineItem->asset_variant_id : null;
        $variant = $variantId
            ? AssetVariant::query()->whereKey($variantId)->where('asset_id', $asset->id)->first()
            : null;

        $assetSummary = [
            'display_name' => $asset->display_name ?? $lineItem->name,
            'variant_label' => $variant ? ($variant->display_name ?: $variant->name) : null,
            'year' => $asset->year,
            'make_name' => $asset->make?->display_name,
        ];

        if ($lineItem->customer_asset_options_completed_at) {
            return Inertia::render('Tenant/Public/BoatOptionsSelect', [
                'context' => 'estimate',
                'formTitle' => 'Boat options',
                'recordLabel' => $estimate->display_name,
                'estimate' => [
                    'id' => $estimate->id,
                    'uuid' => $estimate->uuid,
                    'display_name' => $estimate->display_name,
                ],
                'lineItem' => [
                    'position' => (int) $lineItem->position,
                    'name' => $lineItem->name,
                    'completed_at' => $lineItem->customer_asset_options_completed_at->toISOString(),
                    'signer_name' => $lineItem->customer_asset_options_signer_name,
                ],
                'assetSummary' => $assetSummary,
                'account' => $account,
                'logoUrl' => $logoUrl,
                'options' => [],
                'addonsOffered' => [],
                'includeAddonsInForm' => false,
                'submitUrl' => null,
                'alreadyCompleted' => true,
            ]);
        }

        $resolver = app(AssetOptionResolver::class);
        $options = $resolver->resolve($asset, $variant);

        $submitUrl = URL::temporarySignedRoute(
            'estimates.boat-options.submit',
            now()->addDays(30),
            ['uuid' => $estimate->uuid, 'line' => $line]
        );

        return Inertia::render('Tenant/Public/BoatOptionsSelect', [
            'context' => 'estimate',
            'formTitle' => 'Boat options',
            'recordLabel' => $estimate->display_name,
            'estimate' => [
                'id' => $estimate->id,
                'uuid' => $estimate->uuid,
                'display_name' => $estimate->display_name,
            ],
            'lineItem' => [
                'position' => (int) $lineItem->position,
                'name' => $lineItem->name,
            ],
            'assetSummary' => $assetSummary,
            'account' => $account,
            'logoUrl' => $logoUrl,
            'options' => $options,
            'addonsOffered' => [],
            'includeAddonsInForm' => false,
            'submitUrl' => $submitUrl,
            'alreadyCompleted' => false,
        ]);
    }

    public function submitBoatOptionsEstimate(Request $request, string $uuid, int $line)
    {
        $validated = $request->validate([
            'selections' => ['required', 'array'],
            'selections.*.option_id' => ['required', 'integer'],
            'selections.*.option_value_id' => ['required', 'integer'],
            'signer_name' => ['required', 'string', 'max:255'],
            'confirm' => ['sometimes'],
        ]);

        if (! $request->boolean('confirm')) {
            throw ValidationException::withMessages([
                'confirm' => 'Please confirm your selections before submitting.',
            ]);
        }

        $estimate = Estimate::where('uuid', $uuid)->firstOrFail();

        $estimate->loadMissing(['primaryVersion.lineItems']);
        $version = $estimate->primaryVersion;
        if ($version === null) {
            abort(404);
        }

        $lineItem = $version->lineItems->firstWhere('position', $line);
        if ($lineItem === null || ($lineItem->itemable_type ?? '') !== Asset::class) {
            abort(404);
        }

        if (($lineItem->asset_options_fill_mode ?? 'staff') !== 'customer') {
            abort(403);
        }

        if ($lineItem->customer_asset_options_completed_at) {
            return back()->withErrors(['error' => 'Options have already been submitted for this line.']);
        }

        $payload = EstimateSyncPayloadFromVersion::forSelectedOptionSync($estimate);
        $merged = $payload['selected_asset_options'];
        $found = false;
        foreach ($merged as $i => $group) {
            if ((int) $group['line_position'] === $line) {
                $merged[$i]['selections'] = $validated['selections'];
                $found = true;
                break;
            }
        }
        if (! $found) {
            $merged[] = [
                'line_position' => $line,
                'selections' => $validated['selections'],
            ];
        }

        $lineItemsForSync = $payload['line_items'];
        if (isset($lineItemsForSync[$line])) {
            $lineItemsForSync[$line]['asset_options_fill_mode'] = 'staff';
        }

        app(EstimateSelectedOptionSync::class)->sync(
            $estimate,
            $lineItemsForSync,
            $payload['asset_line_items_by_position'],
            $merged,
        );

        $taxRate = (float) ($estimate->tax_rate ?? $version->tax_rate ?? 0);
        RecalculateEstimateVersionTotals::apply($estimate->primaryVersion->fresh(), $taxRate);

        $lineItem->refresh();

        $lineItem->update([
            'customer_asset_options_completed_at' => now(),
            'customer_asset_options_signer_name' => $validated['signer_name'],
            'customer_asset_options_signer_ip' => $request->ip(),
        ]);

        EstimateCustomerOptionSignoff::query()->create([
            'estimate_id' => $estimate->id,
            'transaction_line_item_id' => $lineItem->id,
            'signer_name' => $validated['signer_name'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'signed_at' => now(),
        ]);

        return redirect()->to(URL::temporarySignedRoute(
            'estimates.boat-options',
            now()->addMinutes(30),
            ['uuid' => $estimate->uuid, 'line' => $line]
        ));
    }

    public function featureRequestInviteShow(Request $request, FeatureRequestInvite $invite): Response
    {
        return match ($invite->source) {
            'opportunity' => $this->renderOpportunityFeatureRequestPageFromInvite($invite),
            default => abort(404),
        };
    }

    public function featureRequestInviteSubmit(Request $request, FeatureRequestInvite $invite): \Illuminate\Http\RedirectResponse
    {
        return match ($invite->source) {
            'opportunity' => $this->submitOpportunityFeatureRequestFromInvite($request, $invite),
            default => abort(404),
        };
    }

    public function featureRequestOpportunity(Request $request, string $uuid, int $assetOpportunity, int $includeAddonsFlag): Response
    {
        $includeAddons = $includeAddonsFlag === 1;

        $opportunity = Opportunity::where('uuid', $uuid)->with('customer')->firstOrFail();

        $pivot = DB::table('asset_opportunity')
            ->where('id', $assetOpportunity)
            ->where('opportunity_id', $opportunity->id)
            ->first();

        if ($pivot === null) {
            abort(404);
        }

        return $this->renderOpportunityFeatureRequestPage($opportunity, $pivot, $includeAddons, null);
    }

    private function renderOpportunityFeatureRequestPageFromInvite(FeatureRequestInvite $invite): Response
    {
        $opportunity = Opportunity::with('customer')->findOrFail($invite->opportunity_id);

        $pivot = DB::table('asset_opportunity')
            ->where('id', $invite->asset_opportunity_id)
            ->where('opportunity_id', $opportunity->id)
            ->first();

        abort_if($pivot === null, 404);

        return $this->renderOpportunityFeatureRequestPage($opportunity, $pivot, $invite->include_addons, $invite);
    }

    private function renderOpportunityFeatureRequestPage(
        Opportunity $opportunity,
        object $pivot,
        bool $includeAddons,
        ?FeatureRequestInvite $invite
    ): Response {
        $asset = Asset::query()->with('make:id,display_name')->find((int) $pivot->asset_id);
        if ($asset === null) {
            abort(404);
        }

        $variantId = $pivot->asset_variant_id ? (int) $pivot->asset_variant_id : null;
        $variant = $variantId
            ? AssetVariant::query()->whereKey($variantId)->where('asset_id', $asset->id)->first()
            : null;

        $variantLabel = $variant ? ($variant->display_name ?: $variant->name) : null;

        $account = AccountSettings::getCurrent();
        $logoUrl = $account->logo_url ?? null;

        $completedRaw = $pivot->feature_request_completed_at ?? null;

        $lastSubmission = OpportunityFeatureRequest::query()
            ->where('asset_opportunity_id', $pivot->id)
            ->orderByDesc('submitted_at')
            ->first();

        if ($completedRaw) {
            return Inertia::render('Tenant/Public/BoatOptionsSelect', [
                'context' => 'opportunity',
                'formTitle' => 'Feature Request Form',
                'recordLabel' => $opportunity->display_name,
                'estimate' => null,
                'lineItem' => [
                    'position' => 0,
                    'name' => $asset->display_name ?? $asset->name ?? 'Asset',
                    'completed_at' => \Carbon\Carbon::parse((string) $completedRaw)->toISOString(),
                    'signer_name' => $lastSubmission?->signer_name,
                ],
                'assetSummary' => [
                    'display_name' => $asset->display_name ?? $asset->name,
                    'variant_label' => $variantLabel,
                    'year' => $asset->year,
                    'make_name' => $asset->make?->display_name,
                ],
                'account' => $account,
                'logoUrl' => $logoUrl,
                'options' => [],
                'addonsOffered' => [],
                'includeAddonsInForm' => false,
                'submitUrl' => null,
                'alreadyCompleted' => true,
            ]);
        }

        $resolver = app(AssetOptionResolver::class);
        $options = $resolver->resolve($asset, $variant);

        $addonsOffered = [];
        if ($includeAddons) {
            $allowedCatalogIds = $this->whitelistCatalogIdsFromInviteOrPivot($invite, $pivot);
            if ($allowedCatalogIds !== []) {
                $addonsOffered = AddOn::query()
                    ->whereIn('id', $allowedCatalogIds)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (AddOn $a) => [
                        'catalog_addon_id' => $a->id,
                        'name' => $a->name,
                        'price' => (float) ($a->default_price ?? 0),
                        'quantity_default' => 1,
                    ])
                    ->values()
                    ->all();
            }
        }

        $showAddonSection = $includeAddons && $addonsOffered !== [];

        $submitExpiry = now()->addDays(30);
        $submitUrl = $invite !== null
            ? URL::temporarySignedRoute('featurerequest.submit', $submitExpiry, ['invite' => $invite->uuid])
            : URL::temporarySignedRoute(
                'opportunities.feature-request.submit',
                $submitExpiry,
                [
                    'uuid' => $opportunity->uuid,
                    'assetOpportunity' => $pivot->id,
                    'includeAddons' => $includeAddons ? 1 : 0,
                ]
            );

        return Inertia::render('Tenant/Public/BoatOptionsSelect', [
            'context' => 'opportunity',
            'formTitle' => 'Feature Request Form',
            'recordLabel' => $opportunity->display_name,
            'estimate' => null,
            'lineItem' => [
                'position' => 0,
                'name' => $asset->display_name ?? $asset->name ?? 'Asset',
            ],
            'assetSummary' => [
                'display_name' => $asset->display_name ?? $asset->name,
                'variant_label' => $variantLabel,
                'year' => $asset->year,
                'make_name' => $asset->make?->display_name,
            ],
            'account' => $account,
            'logoUrl' => $logoUrl,
            'options' => $options,
            'addonsOffered' => $addonsOffered,
            'includeAddonsInForm' => $showAddonSection,
            'submitUrl' => $submitUrl,
            'alreadyCompleted' => false,
        ]);
    }

    /**
     * @return array<int>
     */
    private function whitelistCatalogIdsFromInviteOrPivot(?FeatureRequestInvite $invite, object $pivot): array
    {
        if ($invite !== null && is_array($invite->addon_catalog_ids) && $invite->addon_catalog_ids !== []) {
            return array_values(array_unique(array_map('intval', $invite->addon_catalog_ids)));
        }

        return $this->resolveFeatureRequestAddonWhitelistCatalogIds($pivot);
    }

    public function submitFeatureRequestOpportunity(Request $request, string $uuid, int $assetOpportunity, int $includeAddonsFlag): \Illuminate\Http\RedirectResponse
    {
        $includeAddons = $includeAddonsFlag === 1;

        $rules = [
            'selections' => ['required', 'array'],
            'selections.*.option_id' => ['required', 'integer'],
            'selections.*.option_value_id' => ['required', 'integer'],
            'signer_name' => ['required', 'string', 'max:255'],
            'confirm' => ['sometimes'],
        ];

        if ($includeAddons) {
            $rules['addon_selections'] = ['nullable', 'array'];
            $rules['addon_selections.*.quantity'] = ['required', 'integer', 'min:1'];
            $rules['addon_selections.*.catalog_addon_id'] = ['sometimes', 'nullable', 'integer'];
            $rules['addon_selections.*.opportunity_asset_addon_id'] = ['sometimes', 'nullable', 'integer'];
        }

        $validated = $request->validate($rules);

        if (! $request->boolean('confirm')) {
            throw ValidationException::withMessages([
                'confirm' => 'Please confirm before submitting.',
            ]);
        }

        $opportunity = Opportunity::where('uuid', $uuid)->firstOrFail();

        $pivot = DB::table('asset_opportunity')
            ->where('id', $assetOpportunity)
            ->where('opportunity_id', $opportunity->id)
            ->first();

        if ($pivot === null) {
            abort(404);
        }

        return $this->completeOpportunityFeatureRequestSubmission($request, $opportunity, $pivot, $includeAddons, null, $validated);
    }

    private function submitOpportunityFeatureRequestFromInvite(Request $request, FeatureRequestInvite $invite): \Illuminate\Http\RedirectResponse
    {
        $rules = [
            'selections' => ['required', 'array'],
            'selections.*.option_id' => ['required', 'integer'],
            'selections.*.option_value_id' => ['required', 'integer'],
            'signer_name' => ['required', 'string', 'max:255'],
            'confirm' => ['sometimes'],
        ];

        if ($invite->include_addons) {
            $rules['addon_selections'] = ['nullable', 'array'];
            $rules['addon_selections.*.quantity'] = ['required', 'integer', 'min:1'];
            $rules['addon_selections.*.catalog_addon_id'] = ['sometimes', 'nullable', 'integer'];
            $rules['addon_selections.*.opportunity_asset_addon_id'] = ['sometimes', 'nullable', 'integer'];
        }

        $validated = $request->validate($rules);

        if (! $request->boolean('confirm')) {
            throw ValidationException::withMessages([
                'confirm' => 'Please confirm before submitting.',
            ]);
        }

        $opportunity = Opportunity::findOrFail($invite->opportunity_id);

        $pivot = DB::table('asset_opportunity')
            ->where('id', $invite->asset_opportunity_id)
            ->where('opportunity_id', $opportunity->id)
            ->first();

        abort_if($pivot === null, 404);

        return $this->completeOpportunityFeatureRequestSubmission(
            $request,
            $opportunity,
            $pivot,
            $invite->include_addons,
            $invite,
            $validated
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function completeOpportunityFeatureRequestSubmission(
        Request $request,
        Opportunity $opportunity,
        object $pivot,
        bool $includeAddons,
        ?FeatureRequestInvite $invite,
        array $validated
    ): \Illuminate\Http\RedirectResponse {
        if ($pivot->feature_request_completed_at ?? null) {
            return back()->withErrors(['error' => 'A response has already been submitted for this request.']);
        }

        $asset = Asset::query()->with('make:id,display_name')->find((int) $pivot->asset_id);
        abort_if($asset === null, 404);

        $variantId = $pivot->asset_variant_id ? (int) $pivot->asset_variant_id : null;
        $variant = $variantId
            ? AssetVariant::query()->whereKey($variantId)->where('asset_id', $asset->id)->first()
            : null;

        $resolver = app(AssetOptionResolver::class);
        $resolved = $resolver->resolve($asset, $variant);

        $this->validatePublicAssetSelections($resolved, $validated['selections']);

        $addonPayload = [];
        if ($includeAddons) {
            $allowedCatalogIds = $this->whitelistCatalogIdsFromInviteOrPivot($invite, $pivot);
            $allowedCatalogIdSet = array_fill_keys($allowedCatalogIds, true);

            foreach ($validated['addon_selections'] ?? [] as $row) {
                $qty = max(1, (int) ($row['quantity'] ?? 1));
                $catalogId = null;

                if (! empty($row['catalog_addon_id'])) {
                    $catalogId = (int) $row['catalog_addon_id'];
                } elseif (! empty($row['opportunity_asset_addon_id'])) {
                    $oaa = OpportunityAssetAddon::query()
                        ->where('asset_opportunity_id', $pivot->id)
                        ->whereKey((int) $row['opportunity_asset_addon_id'])
                        ->first();
                    $catalogId = $oaa?->addon_id !== null ? (int) $oaa->addon_id : null;
                }

                if ($catalogId === null) {
                    throw ValidationException::withMessages(['addon_selections' => 'Invalid add-on selection.']);
                }

                if (! isset($allowedCatalogIdSet[$catalogId])) {
                    throw ValidationException::withMessages(['addon_selections' => 'Invalid add-on selection.']);
                }

                $addonPayload[] = [
                    'catalog_addon_id' => $catalogId,
                    'quantity' => $qty,
                ];
            }
        }

        $variantLabel = $variant ? ($variant->display_name ?: $variant->name) : null;

        $submission = DB::transaction(function () use (
            $opportunity,
            $pivot,
            $includeAddons,
            $asset,
            $variantLabel,
            $validated,
            $addonPayload,
            $request
        ): OpportunityFeatureRequest {
            $submission = OpportunityFeatureRequest::query()->create([
                'opportunity_id' => $opportunity->id,
                'asset_opportunity_id' => $pivot->id,
                'include_addons' => $includeAddons,
                'asset_display_name' => $asset->display_name ?? $asset->name,
                'variant_label' => $variantLabel,
                'asset_option_selections' => $validated['selections'],
                'addon_selections' => $addonPayload !== [] ? $addonPayload : null,
                'signer_name' => $validated['signer_name'],
                'signer_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'submitted_at' => now(),
            ]);

            DB::table('asset_opportunity')
                ->where('id', $pivot->id)
                ->update(['feature_request_completed_at' => now()]);

            return $submission;
        });

        try {
            $this->notifications->notifyOpportunityFeatureRequestSubmitted(
                $opportunity->fresh(['customer', 'salesperson']),
                $submission,
                AccountSettings::getCurrent()
            );
        } catch (\Throwable $e) {
            Log::error('Failed to notify salesperson of feature request submission', [
                'opportunity_id' => $opportunity->id,
                'feature_request_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }

        $thanksExpiry = now()->addMinutes(30);

        if ($invite !== null) {
            return redirect()->to(URL::temporarySignedRoute(
                'featurerequest.show',
                $thanksExpiry,
                ['invite' => $invite->uuid]
            ));
        }

        return redirect()->to(URL::temporarySignedRoute(
            'opportunities.feature-request.show',
            $thanksExpiry,
            [
                'uuid' => $opportunity->uuid,
                'assetOpportunity' => $pivot->id,
                'includeAddons' => $includeAddons ? 1 : 0,
            ]
        ));
    }

    /**
     * Resolve catalog add-on IDs allowed for this feature-request invite.
     * New invites store catalog `addons.id` values in JSON; legacy rows stored opportunity_asset_addon ids.
     *
     * @return array<int>
     */
    private function resolveFeatureRequestAddonWhitelistCatalogIds(object $pivot): array
    {
        $raw = $pivot->feature_request_addon_ids ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }

        $ids = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($ids) || $ids === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        $catalogMatches = AddOn::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($catalogMatches !== []) {
            return array_values(array_intersect($ids, $catalogMatches));
        }

        return OpportunityAssetAddon::query()
            ->where('asset_opportunity_id', $pivot->id)
            ->whereIn('id', $ids)
            ->get()
            ->pluck('addon_id')
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delivery Review / Sign
    // ─────────────────────────────────────────────────────────────────────────

    public function reviewDelivery(Request $request, $uuid)
    {
        $delivery = Delivery::where('uuid', $uuid)->firstOrFail();

        $delivery->load([
            'customer',
            'subsidiary',
            'location',
            'assetUnit.asset.make',
            'items' => function ($query) {
                $query->orderBy('position')
                    ->with([
                        'assetUnit.asset.make',
                        'assetUnit.assetVariant',
                        'assetVariant',
                    ]);
            },
        ]);

        $account = AccountSettings::getCurrent();
        $logoUrl = $this->resolveLogoUrl($delivery, $account);

        $recordArray = [
            'id' => $delivery->id,
            'uuid' => $delivery->uuid,
            'display_name' => $delivery->display_name,
            'customer_id' => $delivery->customer_id,
            'asset_unit_id' => $delivery->asset_unit_id,
            'work_order_id' => $delivery->work_order_id,
            'scheduled_at' => $delivery->scheduled_at?->toISOString(),
            'estimated_arrival_at' => $delivery->estimated_arrival_at?->toISOString(),
            'delivered_at' => $delivery->delivered_at?->toISOString(),
            'status' => $delivery->status,
            'technician_id' => $delivery->technician_id,
            'recipient_name' => $delivery->recipient_name,
            'signature_path' => $delivery->signature_path,
            'signed_at' => $delivery->signed_at?->toISOString(),
            'signed_ip' => $delivery->signed_ip,
            'signed_user_agent' => $delivery->signed_user_agent,
            'signature_file' => $delivery->signature_file,
            'signature_hash' => $delivery->signature_hash,
            'internal_notes' => $delivery->internal_notes,
            'customer_notes' => $delivery->customer_notes,
            'address_line_1' => $delivery->address_line_1,
            'address_line_2' => $delivery->address_line_2,
            'city' => $delivery->city,
            'state' => $delivery->state,
            'postal_code' => $delivery->postal_code,
            'country' => $delivery->country,
            'latitude' => $delivery->latitude,
            'longitude' => $delivery->longitude,
            'subsidiary_id' => $delivery->subsidiary_id,
            'location_id' => $delivery->location_id,
            'created_at' => $delivery->created_at?->toISOString(),
            'updated_at' => $delivery->updated_at?->toISOString(),
            'customer' => $delivery->customer?->toArray(),
            'subsidiary' => $delivery->subsidiary?->toArray(),
            'location' => $delivery->location?->toArray(),
            'assetUnit' => $delivery->assetUnit?->toArray(),
            'asset_unit' => $delivery->assetUnit?->toArray(),
            'items' => $delivery->items->map->toArray()->values()->all(),
            'signature_url' => $delivery->signature_url,
        ];

        return Inertia::render('Tenant/Public/DeliveryReview', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
            'enumOptions' => [],
        ]);
    }

    public function signDelivery(Request $request, $uuid)
    {
        $delivery = Delivery::where('uuid', $uuid)->firstOrFail();

        if ($delivery->signed_at) {
            return back();
        }

        $request->validate([
            'signature_method' => 'required|in:draw,type',
            'signature_data' => 'required|string',
            'signed_name' => 'required|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'consent' => 'required|accepted',
        ]);

        $account = AccountSettings::getCurrent();

        $signatureFile = null;
        if ($request->signature_method === 'draw') {
            $signatureFile = $this->storeSignatureImage($request->signature_data, $delivery->uuid);
        }

        $deliveredAt = $delivery->delivered_at ?? now();

        $delivery->update([
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
            'signed_user_agent' => $request->userAgent(),
            'recipient_name' => $request->recipient_name,
            'signature_file' => $signatureFile,
            'signature_hash' => hash('sha256', json_encode([
                'delivery_id' => $delivery->id,
                'uuid' => $delivery->uuid,
                'signed_name' => $request->signed_name,
                'recipient_name' => $request->recipient_name,
                'timestamp' => now()->toISOString(),
                'ip' => $request->ip(),
            ])),
            'status' => DeliveryStatus::Delivered->value,
            'delivered_at' => $deliveredAt,
        ]);

        $delivery->refresh();

        $this->notifications->notifyDeliverySigned($delivery, $account);

        return back();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Contract Review / Sign
    // ─────────────────────────────────────────────────────────────────────────

    public function reviewContract(Request $request, string $uuid)
    {
        $contract = Contract::where('uuid', $uuid)
            ->with([
                'customer',
                'transaction' => fn ($q) => $q
                    ->select([
                        'id', 'title', 'sequence', 'customer_name', 'customer_email', 'customer_phone',
                        'tax_rate', 'currency', 'subsidiary_id', 'location_id',
                        'billing_address_line1', 'billing_address_line2', 'billing_city',
                        'billing_state', 'billing_postal', 'billing_country',
                    ])
                    ->with([
                        'items' => fn ($q2) => $q2->with('addons')->orderBy('position')->orderBy('id'),
                        'subsidiary' => fn ($q2) => $q2->select(['id', 'display_name']),
                        'location' => fn ($q2) => $q2->select([
                            'id', 'display_name',
                            'address_line_1', 'address_line_2', 'city', 'state', 'postal_code',
                            'phone', 'email',
                        ]),
                    ]),
            ])
            ->firstOrFail();

        $account = AccountSettings::getCurrent();
        $logoUrl = $contract->transaction?->subsidiary?->logo_url ?? $account->logo_url;

        $recordArray = $contract->toArray();
        $recordArray['created_at'] = $contract->created_at?->toISOString();
        $recordArray['updated_at'] = $contract->updated_at?->toISOString();
        $recordArray['signed_at'] = $contract->signed_at?->toISOString();
        $recordArray['signature_url'] = $contract->signature_file
            ? Storage::disk('s3')->temporaryUrl($contract->signature_file, now()->addHours(2))
            : null;

        return Inertia::render('Tenant/Public/ContractReview', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
        ]);
    }

    public function signContract(Request $request, string $uuid)
    {
        $contract = Contract::where('uuid', $uuid)->firstOrFail();

        if ($contract->signed_at) {
            return back();
        }

        $request->validate([
            'signature_method' => 'required|in:draw,type',
            'signature_data' => 'required|string',
            'signed_name' => 'required|string|max:255',
            'consent' => 'required|accepted',
        ]);

        $account = AccountSettings::getCurrent();

        $signatureMethod = $request->signature_method === 'draw' ? 1 : 5;
        $signatureFile = null;
        if ($request->signature_method === 'draw') {
            $signatureFile = $this->storeSignatureImage($request->signature_data, $contract->uuid);
        }

        $signatureHash = hash('sha256', json_encode([
            'contract_id' => $contract->id,
            'uuid' => $contract->uuid,
            'total_amount' => (string) $contract->total_amount,
            'signed_name' => $request->signed_name,
            'timestamp' => now()->toISOString(),
            'ip' => $request->ip(),
        ]));

        $contract->update([
            'status' => ContractStatus::Signed->value,
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
            'signed_user_agent' => $request->userAgent(),
            'signed_name' => $request->signed_name,
            'customer_signature' => $request->signature_method === 'type' ? $request->signature_data : null,
            'signature_method' => $signatureMethod,
            'signature_hash' => $signatureHash,
            'signature_file' => $signatureFile,
        ]);

        $contract->refresh();

        $this->notifications->notifyContractSigned($contract, $account);

        return back();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Consignment agreement (public review / sign)
    // ─────────────────────────────────────────────────────────────────────────

    public function reviewConsignmentAgreement(Request $request, string $uuid): Response
    {
        $agreement = ConsignmentAgreement::query()
            ->where('uuid', $uuid)
            ->with([
                'assetUnit' => fn ($q) => $q->with([
                    'asset' => fn ($aq) => $aq->select(['id', 'display_name', 'year', 'make_id'])
                        ->with(['make' => fn ($mq) => $mq->select(['id', 'display_name'])]),
                    'assetVariant' => fn ($vq) => $vq->select(['id', 'display_name', 'name']),
                    'customer' => fn ($cq) => $cq->select(['id']),
                    'subsidiary' => fn ($sq) => $sq->select(['id', 'display_name', 'logo']),
                ]),
            ])
            ->firstOrFail();

        abort_unless($agreement->assetUnit?->is_consignment, 404);

        $account = AccountSettings::getCurrent();
        $policies = ConsignmentPolicy::query()
            ->active()
            ->ordered()
            ->get(['id', 'body', 'sort_order']);

        $unit = $agreement->assetUnit;
        $logoUrl = $unit?->subsidiary?->logo_url ?? $account->logo_url;

        $recordArray = $agreement->toArray();
        $recordArray['display_name'] = $agreement->display_name;
        $recordArray['agreement_date'] = $agreement->agreement_date?->toDateString();
        $recordArray['created_at'] = $agreement->created_at?->toISOString();
        $recordArray['updated_at'] = $agreement->updated_at?->toISOString();
        $recordArray['signed_at'] = $agreement->signed_at?->toISOString();
        $recordArray['signature_url'] = $agreement->signature_file
            ? Storage::disk('s3')->temporaryUrl($agreement->signature_file, now()->addHours(2))
            : null;

        return Inertia::render('Tenant/Public/ConsignmentAgreementReview', [
            'record' => $recordArray,
            'account' => $account,
            'logoUrl' => $logoUrl,
            'consignmentPolicies' => $policies->map(fn ($p) => [
                'id' => $p->id,
                'body' => $p->body,
            ])->values()->all(),
        ]);
    }

    public function signConsignmentAgreement(Request $request, string $uuid)
    {
        $agreement = ConsignmentAgreement::query()
            ->where('uuid', $uuid)
            ->with('assetUnit')
            ->firstOrFail();

        abort_unless($agreement->assetUnit?->is_consignment, 404);

        if ($agreement->signed_at) {
            return back();
        }

        $request->validate([
            'signature_method' => 'required|in:draw,type',
            'signature_data' => 'required|string',
            'signed_name' => 'required|string|max:255',
            'consent' => 'required|accepted',
        ]);

        $signatureMethod = $request->signature_method === 'draw' ? 1 : 5;
        $signatureFile = null;
        if ($request->signature_method === 'draw') {
            $signatureFile = $this->storeSignatureImage($request->signature_data, $agreement->uuid);
        }

        $signatureHash = hash('sha256', json_encode([
            'consignment_agreement_id' => $agreement->id,
            'uuid' => $agreement->uuid,
            'asset_unit_id' => $agreement->asset_unit_id,
            'signed_name' => $request->signed_name,
            'timestamp' => now()->toISOString(),
            'ip' => $request->ip(),
        ]));

        $agreement->update([
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
            'signed_user_agent' => $request->userAgent(),
            'signed_name' => $request->signed_name,
            'customer_signature' => $request->signature_method === 'type' ? $request->signature_data : null,
            'signature_method' => $signatureMethod,
            'signature_hash' => $signatureHash,
            'signature_file' => $signatureFile,
        ]);

        $agreement->refresh();

        return back();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveLogoUrl($record, AccountSettings $account): ?string
    {
        if (isset($record->subsidiary) && $record->subsidiary?->logo_url) {
            return $record->subsidiary->logo_url;
        }

        return $account->logo_url;
    }

    private function storeSignatureImage(string $base64Data, string $uuid): ?string
    {
        if (! preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            return null;
        }

        $extension = $matches[1];
        $decoded = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
        if (! $decoded) {
            return null;
        }

        $filename = $uuid.'-signature.'.$extension;
        $key = "private/signatures/{$filename}";

        try {
            $s3Client = Storage::disk('s3')->getClient();
            $s3Client->putObject([
                'Bucket' => Storage::disk('s3')->getConfig()['bucket'],
                'Key' => $key,
                'Body' => $decoded,
                'ContentType' => "image/{$extension}",
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to store signature image: '.$e->getMessage());

            return null;
        }

        return $key;
    }

    public function viewInvoice(Request $request, string $uuid, FulfillPublicInvoiceCheckoutSession $fulfillCheckout)
    {
        $invoice = Invoice::query()
            ->where('uuid', $uuid)
            ->with(Invoice::documentEagerLoads())
            ->firstOrFail();

        $sessionId = $request->query('session_id');
        if (is_string($sessionId) && str_starts_with($sessionId, 'cs_')) {
            $result = $fulfillCheckout($invoice, $sessionId);
            $redirect = redirect()->route('invoices.view', ['uuid' => $uuid]);

            if ($result['ok']) {
                return $redirect->with('success', 'Thank you — your payment was received.');
            }

            $status = $result['status'] ?? null;
            $message = $result['message'] ?? 'Payment could not be confirmed.';

            if ($status === 'processing') {
                return $redirect
                    ->with('info', $message)
                    ->with('checkout_refresh', true);
            }

            if ($result['checkout_refresh'] ?? false) {
                $redirect = $redirect->with('checkout_refresh', true);
            }

            return $redirect->with('error', $message);
        }

        $invoice->markAsViewed();
        $invoice = $invoice->fresh(Invoice::documentEagerLoads()) ?? $invoice;

        $account = AccountSettings::getCurrent();
        $canPayOnline = InvoicePayOnline::canPayOnline($invoice);
        $payOnlineUi = InvoicePayOnline::payOnlineUiFlags($invoice);

        return Inertia::render('Tenant/Public/InvoiceView', [
            'record' => $invoice,
            'account' => $account,
            'logoUrl' => $account->logo_url ?? null,
            'enumOptions' => [
                Terms::class => Terms::options(),
                InvoiceStatus::class => InvoiceStatus::options(),
            ],
            'canPayOnline' => $canPayOnline,
            'payOnlineUi' => $payOnlineUi,
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

    public function startInvoicePayment(Request $request, string $uuid, StripeService $stripe)
    {
        $invoice = Invoice::query()->where('uuid', $uuid)->firstOrFail();
        $invoice->refresh();

        if (! InvoicePayOnline::canPayOnline($invoice)) {
            return back()->with('error', 'This invoice cannot be paid online.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $principal = round((float) $validated['amount'], 2);
        $amountDue = round((float) $invoice->amount_due, 2);

        if ($principal > $amountDue + 0.01) {
            return back()->withErrors(['amount' => 'Amount cannot exceed the balance due.']);
        }

        if (! $invoice->allow_partial_payment && abs($principal - $amountDue) > 0.02) {
            return back()->withErrors(['amount' => 'This invoice must be paid in full.']);
        }

        if ($invoice->allow_partial_payment && $invoice->minimum_partial_amount !== null) {
            $min = round((float) $invoice->minimum_partial_amount, 2);
            if ($principal + 0.0001 < $min) {
                return back()->withErrors([
                    'amount' => 'Amount must be at least '.number_format($min, 2).'.',
                ]);
            }
        }

        $surchargePct = (float) ($invoice->surcharge_percent ?? 0);
        $surcharge = round($principal * $surchargePct / 100, 2);
        $total = round($principal + $surcharge, 2);
        $totalCents = (int) round($total * 100);

        if ($totalCents < 50) {
            return back()->withErrors(['amount' => 'Amount is below the minimum for online payment.']);
        }

        $config = PaymentConfiguration::forStripe();

        if (! InvoicePayOnline::invoiceAcceptsStripeOnline($invoice)) {
            return back()->with('error', 'Online payment is not enabled for this invoice.');
        }

        $paymentMethodTypes = InvoicePayOnline::stripeCheckoutPaymentMethodTypes($invoice);

        $base = route('invoices.view', ['uuid' => $invoice->uuid]);
        $successUrl = $base.(str_contains($base, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $base;

        try {
            $checkoutUrl = $stripe->createInvoiceCheckoutSession(
                $config,
                $invoice,
                $totalCents,
                [
                    'principal' => number_format($principal, 2, '.', ''),
                    'surcharge' => number_format($surcharge, 2, '.', ''),
                ],
                $successUrl,
                $cancelUrl,
                $paymentMethodTypes,
            );
        } catch (\Throwable $e) {
            Log::error('Invoice checkout create failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not start payment. Please try again later.');
        }

        return Inertia::location($checkoutUrl);
    }

    private function validatePublicAssetSelections(Collection $resolved, array $selections): void
    {
        $byOption = collect($selections)->groupBy(fn ($s) => (int) $s['option_id']);

        foreach ($resolved as $opt) {
            $oid = (int) $opt['option_id'];
            $required = (bool) ($opt['is_required'] ?? false);
            $picked = $byOption->get($oid, collect());
            if ($required && $picked->isEmpty()) {
                throw ValidationException::withMessages([
                    'selections' => 'Option "'.($opt['name'] ?? 'Unknown').'" is required.',
                ]);
            }

            foreach ($picked as $row) {
                $vid = (int) $row['option_value_id'];
                $match = collect($opt['values'] ?? [])->firstWhere('id', $vid);
                if ($match === null) {
                    throw ValidationException::withMessages([
                        'selections' => 'Invalid selection for "'.($opt['name'] ?? 'Unknown').'".',
                    ]);
                }
            }
        }
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
