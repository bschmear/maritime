<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Models\EstimateSelectedOption;
use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Estimate\Actions\CreateDealFromEstimate;
use App\Domain\Estimate\Actions\CreateEstimate as CreateAction;
use App\Domain\Estimate\Actions\DeleteEstimate as DeleteAction;
use App\Domain\Estimate\Actions\UpdateEstimate as UpdateAction;
use App\Domain\Estimate\Models\Estimate as RecordModel;
use App\Domain\Estimate\Models\EstimateLineItem;
use App\Domain\Estimate\Models\EstimateLineItemAddon;
use App\Domain\Estimate\Models\EstimateVersion;
use App\Domain\Location\Models\Location;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Enums\Estimate\EstimateStatus;
use App\Enums\Timezone;
use App\Mail\EstimateApprovalRequest;
use App\Mail\EstimateBoatOptionsInvite;
use App\Models\AccountSettings;
use App\Services\Mail\TenantMailService;
use App\Services\SMS\SmsService;
use App\Services\TenantStaffResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\MessageBag;

class EstimateController extends RecordController
{
    protected $recordType = 'Estimate';

    protected $table = null;

    /**
     * @return array<int, array{line_position: int, selections: array<int, array{option_id: int, option_value_id: int}>}>
     */
    protected function buildInitialSelectedAssetOptions(RecordModel $estimate): array
    {
        $estimate->loadMissing([
            'selectedAssetOptions',
            'primaryVersion.lineItems',
        ]);

        $version = $estimate->primaryVersion;
        if ($version === null) {
            return [];
        }

        $byLine = $estimate->selectedAssetOptions->groupBy('transaction_line_item_id');
        $out = [];

        foreach ($version->lineItems as $li) {
            if (($li->itemable_type ?? '') !== Asset::class) {
                continue;
            }

            $group = $byLine->get($li->id);
            if ($group === null || $group->isEmpty()) {
                continue;
            }

            $out[] = [
                'line_position' => (int) $li->position,
                'selections' => $group->map(fn ($s) => [
                    'option_id' => $s->option_id,
                    'option_value_id' => $s->option_value_id,
                    'option_name' => $s->option_name,
                    'value_label' => $s->value_label,
                    'price' => $s->price,
                    'taxable' => $s->taxable,
                ])->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Eager-load constraints for estimate header + record picks.
     * getRelationshipsToLoad() returns a list with numeric keys; normalize so isset($relationships[$name]) works.
     */
    protected function buildEstimateRecordEagerRelationships(array $fieldsSchema): array
    {
        $relationships = [];
        foreach (array_unique($this->getRelationshipsToLoad($fieldsSchema)) as $relName) {
            if (! is_string($relName)) {
                continue;
            }
            $typeDomain = null;
            foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                if (($fieldDef['type'] ?? '') !== 'record') {
                    continue;
                }
                $rn = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                if ($rn === $relName) {
                    $typeDomain = $fieldDef['typeDomain'] ?? null;
                    break;
                }
            }
            if ($typeDomain === 'Customer') {
                $relationships[$relName] = Customer::eagerWithContactSelect();
            } elseif ($typeDomain === 'User') {
                $relationships[$relName] = fn ($q) => $q->select(['id', 'display_name', 'first_name', 'last_name', 'email']);
            } else {
                $relationships[$relName] = fn ($q) => $q->select(['id', 'display_name']);
            }
        }

        return $relationships;
    }

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'estimates',
            'Estimate',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType
        );
    }

    /**
     * Search estimates by sequence number or display_name format ("EST-123" or "123").
     */
    protected function applyCustomSearch($query, string $rawSearch): bool
    {
        // Strip the "EST-" prefix if present so "EST-42" and "42" both match sequence 42
        $normalized = preg_replace('/^EST-/i', '', trim($rawSearch));
        $like = '%'.strtolower($normalized).'%';

        $query->where(function ($q) use ($normalized, $like) {
            $q->whereRaw('CAST(sequence AS TEXT) LIKE ?', [$like])
                ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$like]);

            // If numeric, also match exact sequence value for faster hits
            if (ctype_digit($normalized)) {
                $q->orWhere('sequence', '=', (int) $normalized);
            }
        });

        return true;
    }

    public function store(Request $request, $publicStorage = null)
    {
        $data = $request->all();

        $result = $this->createAction->__invoke($data);

        if ($result['success']) {
            return redirect()->route('estimates.show', $result['record']->id)
                ->with('success', 'Estimate created successfully.');
        }

        $bag = new MessageBag;
        foreach ($result['errors'] ?? [] as $key => $messages) {
            foreach (Arr::wrap($messages) as $m) {
                $bag->add($key, $m);
            }
        }
        if (! empty($result['message'])) {
            $bag->add('error', $result['message']);
        }

        return back()->withErrors($bag)->withInput();
    }

    public function create()
    {
        $request = request();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

        $initialData = [];
        $opportunityLineItems = null;

        if ($request->query('from') === 'opportunity' && $request->query('id')) {
            $opportunity = Opportunity::with([
                'customer',
                'createdBy',
                'salesperson',
                'assets' => fn ($q) => $q->with('make:id,display_name')->withPivot('id', 'quantity', 'unit_price', 'estimated_cost', 'notes', 'asset_variant_id', 'asset_unit_id'),
                'inventoryItems' => fn ($q) => $q->withPivot('id', 'quantity', 'unit_price', 'estimated_cost', 'notes'),
            ])->find($request->query('id'));

            if ($opportunity) {
                Opportunity::hydratePivotAssetVariants($opportunity->assets);
                Opportunity::attachLineItemSnapshotsForJson($opportunity);
                $tenantUser = current_tenant_profile();

                $initialData = [
                    'opportunity_id' => $opportunity->id,
                    'opportunity' => ['id' => $opportunity->id, 'display_name' => $opportunity->display_name],
                    'user_id' => $tenantUser?->id,
                    'user' => $tenantUser ? ['id' => $tenantUser->id, 'display_name' => $tenantUser->display_name ?? ''] : null,
                ];

                if ($opportunity->customer_id) {
                    $initialData['customer_id'] = $opportunity->customer_id;
                    $initialData['customer'] = ['id' => $opportunity->customer->id, 'display_name' => $opportunity->customer->display_name];

                    // Populate contact_id so the contact-first picker pre-fills.
                    $contactId = $opportunity->customer->contact_id;
                    if ($contactId) {
                        $contact = $opportunity->customer->contact ?? Contact::find($contactId);
                        if ($contact) {
                            $initialData['contact_id'] = $contact->id;
                            $initialData['contact'] = ['id' => $contact->id, 'display_name' => $contact->display_name];
                        }
                    }
                }

                $opportunityLineItems = [
                    'assets' => $opportunity->assets ?? [],
                    'inventoryItems' => $opportunity->inventoryItems ?? [],
                ];
            }
        }

        // Pre-fill subsidiary + location from the first available subsidiary (single-subsidiary tenant default).
        if (empty($initialData['subsidiary_id'])) {
            $sub = Subsidiary::query()->orderBy('id')->first();

            if ($sub) {
                $initialData['subsidiary_id'] = $sub->id;
                $initialData['subsidiary'] = ['id' => $sub->id, 'display_name' => $sub->display_name];

                $loc = $sub->locations()->wherePivot('primary', true)->first()
                    ?? $sub->locations()->first();

                if ($loc) {
                    $initialData['location_id'] = $loc->id;
                    $initialData['location'] = ['id' => $loc->id, 'display_name' => $loc->display_name];
                }
            }
        }

        return inertia('Tenant/Estimate/Create', [
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'initialData' => $initialData,
            'opportunityLineItems' => $opportunityLineItems,
            'initialSelectedAssetOptions' => [],
        ]);
    }

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

        $relationships = $this->buildEstimateRecordEagerRelationships($fieldsSchema);

        $relationships['primaryVersion'] = fn ($q) => $q->with([
            'lineItems' => fn ($q2) => $q2->with([
                'addons.addon:id,name,default_price',
                'itemable',
                'assetVariant',
                'assetUnit',
                'selectedAssetOptions' => fn ($q3) => $q3
                    ->select([
                        'id',
                        'estimate_id',
                        'transaction_line_item_id',
                        'option_id',
                        'option_value_id',
                        'option_name',
                        'value_label',
                        'price',
                        'taxable',
                    ])
                    ->orderBy('id'),
            ]),
        ]);
        $relationships['revision'] = fn ($q) => $q->select('id', 'sequence', 'revised_from_id');
        $relationships['revisedFrom'] = fn ($q) => $q->select('id', 'sequence');
        $relationships['contact'] = fn ($q) => $q->select(['id', 'display_name', 'first_name', 'last_name', 'email', 'phone']);
        $relationships['subsidiary'] = fn ($q) => $q->select(['id', 'display_name']);
        $relationships['location'] = fn ($q) => $q->select(['id', 'display_name']);

        // customer_id is on the form but not in fields.json — load explicitly for Show UI / links.
        $relationships['customer'] = Customer::eagerWithContactSelect(['email', 'phone', 'mobile']);

        $relationships['selectedAssetOptions'] = fn ($q) => $q
            ->select([
                'id',
                'estimate_id',
                'transaction_line_item_id',
                'option_id',
                'option_value_id',
                'option_name',
                'value_label',
                'price',
                'taxable',
            ])
            ->orderBy('id');

        $relationships['customerBoatOptionSignoffs'] = fn ($q) => $q
            ->select(['id', 'estimate_id', 'transaction_line_item_id', 'signer_name', 'signed_at', 'ip_address'])
            ->orderByDesc('signed_at');

        $record = $this->recordModel->with($relationships)->findOrFail($id);

        $smsService = app(SmsService::class);
        $estimateApprovalSms = $smsService->estimateApprovalSmsCanBeOffered($record->customer, $request->user());

        return inertia('Tenant/Estimate/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'estimateApprovalSms' => $estimateApprovalSms,
        ]);
    }

    public function edit($id)
    {
        $estimate = RecordModel::withOnly(['revision:id,revised_from_id'])->findOrFail($id);

        if ($estimate->is_locked) {
            return redirect()->route('estimates.show', $id)
                ->withErrors(['locked' => 'This estimate is locked because it has been sent for approval. Create a revision to make changes.']);
        }

        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

        $relationships = $this->buildEstimateRecordEagerRelationships($fieldsSchema);

        $relationships['primaryVersion'] = fn ($q) => $q->with([
            'lineItems' => fn ($q2) => $q2->with([
                'addons.addon:id,name,default_price',
                'itemable',
                'assetVariant',
                'assetUnit',
                'selectedAssetOptions' => fn ($q3) => $q3
                    ->select([
                        'id',
                        'estimate_id',
                        'transaction_line_item_id',
                        'option_id',
                        'option_value_id',
                        'option_name',
                        'value_label',
                        'price',
                        'taxable',
                    ])
                    ->orderBy('id'),
            ]),
        ]);
        $relationships['contact'] = fn ($q) => $q->select(['id', 'display_name', 'first_name', 'last_name', 'email', 'phone']);
        $relationships['subsidiary'] = fn ($q) => $q->select(['id', 'display_name']);
        $relationships['location'] = fn ($q) => $q->select(['id', 'display_name']);

        $relationships['customer'] = Customer::eagerWithContactSelect();

        $record = $this->recordModel->with($relationships)->findOrFail($id);

        return inertia('Tenant/Estimate/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'initialData' => [],
            'initialSelectedAssetOptions' => $this->buildInitialSelectedAssetOptions($record),
        ]);
    }

    public function update(Request $request, $id, $publicStorage = null)
    {
        $data = $request->all();

        $result = $this->updateAction->__invoke($id, $data);

        if ($result['success']) {
            return redirect()->route('estimates.show', $id)
                ->with('success', 'Estimate updated successfully.');
        }

        $bag = new MessageBag;
        foreach ($result['errors'] ?? [] as $key => $messages) {
            foreach (Arr::wrap($messages) as $m) {
                $bag->add($key, $m);
            }
        }
        if (! empty($result['message'])) {
            $bag->add('error', $result['message']);
        }

        return back()->withErrors($bag)->withInput();
    }

    public function sendApprovalRequest(Request $request, $id, SmsService $smsService, TenantMailService $tenantMail)
    {
        $validated = $request->validate([
            'delivery' => 'required|string|in:email,email_sms',
        ]);

        $estimate = RecordModel::with([
            'customer' => Customer::eagerWithContactSelect(['email', 'phone', 'mobile']),
            'primaryVersion',
            'user',
        ])->findOrFail($id);

        $wasPendingApproval = (int) $estimate->status === EstimateStatus::PendingApproval->id();

        $account = AccountSettings::getCurrent();
        $customerEmail = $estimate->customer?->email;
        $approvalProbe = new EstimateApprovalRequest($estimate, $account, 'https://placeholder.invalid');

        if (! $tenantMail->canSend($customerEmail, $approvalProbe, $request->user())) {
            return back()->withErrors(['error' => $tenantMail->validationErrorMessage($approvalProbe)]);
        }

        if ($validated['delivery'] === 'email_sms') {
            $offer = $smsService->estimateApprovalSmsCanBeOffered($estimate->customer, $request->user());
            if (! $offer['offered']) {
                return back()->withErrors([
                    'delivery' => $offer['hint'] ?? 'SMS is not available for this send.',
                ]);
            }
        }

        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;

        if (! $domain) {
            return back()->withErrors(['error' => 'Unable to resolve tenant domain.']);
        }

        $reviewUrl = "https://{$domain}/estimates/{$estimate->uuid}/review";
        $mailable = new EstimateApprovalRequest($estimate, $account, $reviewUrl);

        try {
            $tenantMail->send($customerEmail, $mailable, $request->user());
        } catch (\Exception $e) {
            \Log::error('Failed to send estimate approval request email', [
                'estimate_id' => $estimate->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to send email. Please try again.']);
        }

        $emailTarget = $tenantMail->displayRecipient($customerEmail, $mailable, $request->user());

        $smsNote = '';
        if ($validated['delivery'] === 'email_sms') {
            $result = $smsService->sendEstimateApprovalSms($request->user(), $estimate->customer, $estimate, $reviewUrl);
            if (! $result->success && ($result->status ?? '') === 'not_implemented') {
                $smsNote = ' SMS is not wired yet (Twilio transport); only email was delivered.';
            } elseif (! $result->success) {
                return back()
                    ->with('success', $wasPendingApproval
                        ? "Approval email resent to {$emailTarget}."
                        : "Estimate sent to {$emailTarget} for approval.")
                    ->with('error', 'Email was sent, but SMS failed: '.($result->error ?? 'Unknown error'));
            } else {
                $smsNote = ' A text message was also sent.';
            }
        }

        $estimate->update([
            'sent_at' => now(),
            'status' => EstimateStatus::PendingApproval->id(),
        ]);

        $success = ($wasPendingApproval
            ? "Approval email resent to {$emailTarget}."
            : "Estimate sent to {$emailTarget} for approval.").$smsNote;

        return back()->with('success', $success);
    }

    /**
     * Email signed links so the customer can choose boat options for lines set to "customer" mode.
     */
    public function sendBoatOptionsInvite(Request $request, $id, TenantMailService $tenantMail)
    {
        $validated = $request->validate([
            'line_positions' => ['required', 'array', 'min:1'],
            'line_positions.*' => ['integer', 'min:0'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $estimate = RecordModel::with(['customer', 'primaryVersion.lineItems', 'user'])->findOrFail($id);

        $customerEmail = $estimate->customer?->email;
        $selectedPositions = array_map('intval', $validated['line_positions']);
        $customMessage = isset($validated['message']) ? trim((string) $validated['message']) : '';
        if ($customMessage === '') {
            $customMessage = null;
        }

        $lines = [];
        foreach ($estimate->primaryVersion?->lineItems ?? [] as $li) {
            if (($li->itemable_type ?? '') !== Asset::class) {
                continue;
            }
            if (($li->asset_options_fill_mode ?? 'staff') !== 'customer') {
                continue;
            }
            if ($li->customer_asset_options_completed_at) {
                continue;
            }
            if (! in_array((int) $li->position, $selectedPositions, true)) {
                continue;
            }

            $offeredIds = $li->customer_offered_option_ids ?? [];
            if (! is_array($offeredIds) || $offeredIds === []) {
                return back()->withErrors([
                    'error' => 'Configure which options the customer should fill before sending (line '.(((int) $li->position) + 1).').',
                ]);
            }

            $label = trim(($li->name ?: 'Boat').' (line '.(((int) $li->position) + 1).')');
            $url = URL::temporarySignedRoute(
                'estimates.boat-options',
                now()->addDays(30),
                ['uuid' => $estimate->uuid, 'line' => (int) $li->position]
            );
            $lines[] = ['label' => $label, 'url' => $url];
        }

        if ($lines === []) {
            return back()->withErrors([
                'error' => 'No boat lines are waiting on customer option selections. Mark lines as “customer chooses” on the estimate (and save), or all such lines are already completed.',
            ]);
        }

        $account = AccountSettings::getCurrent();
        $mailable = new EstimateBoatOptionsInvite($estimate, $account, $lines, $customMessage);
        $mailActor = TenantStaffResolver::webUserForMail($request->user());

        if (! $tenantMail->canSend($customerEmail, $mailable, $mailActor)) {
            return back()->withErrors(['error' => $tenantMail->validationErrorMessage($mailable)]);
        }

        try {
            $tenantMail->send($customerEmail, $mailable, $mailActor);
        } catch (\Exception $e) {
            \Log::error('Failed to send boat options invite email', [
                'estimate_id' => $estimate->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to send email. Please try again.']);
        }

        return back()->with('success', 'Boat options link(s) sent to '.$tenantMail->displayRecipient($customerEmail, $mailable, $mailActor).'.');
    }

    public function createRevision(Request $request, $id)
    {
        $original = RecordModel::with([
            'primaryVersion.lineItems.addons',
            'primaryVersion.lineItems.selectedAssetOptions',
            'customer',
        ])->findOrFail($id);

        // Already has a revision — navigate to it
        $existing = RecordModel::where('revised_from_id', $original->id)->first();
        if ($existing) {
            return redirect()->route('estimates.show', $existing->id)
                ->with('info', 'A revision already exists for this estimate.');
        }

        // Copy the full estimate row from raw attributes. `Model::create([...])` can drop keys when
        // `guarded = ['id']` combines with `isGuardableColumn()` (columns missing from the schema
        // cache), which previously stripped contact/subsidiary/location and billing fields.
        $revision = $original->replicateQuietly([
            'uuid',
            'sequence',
            'primary_version_id',
        ]);
        $revision->unsetRelations();

        if (! $revision->contact_id && $original->customer?->contact_id) {
            $revision->contact_id = (int) $original->customer->contact_id;
        }

        $revision->forceFill([
            'status' => EstimateStatus::Draft->id(),
            'revised_from_id' => $original->id,
            'sent_at' => null,
            'signed_at' => null,
            'signed_name' => null,
            'signed_email' => null,
            'signed_ip' => null,
            'signed_user_agent' => null,
            'signature_file' => null,
            'signature_hash' => null,
            'paper_signature_document_id' => null,
            'approved_at' => null,
            'approval_note' => null,
            'declined_at' => null,
            'decline_reason' => null,
            'transaction_id' => null,
        ]);

        $revision->save();

        if ($original->primaryVersion) {
            $pv = $original->primaryVersion;
            $newVersion = EstimateVersion::create([
                'estimate_id' => $revision->id,
                'version' => 1,
                'is_primary' => true,
                'copied_from_version_id' => $pv->id,
                'status' => 'draft',
                'tax_rate' => $pv->tax_rate,
                'subtotal' => $pv->subtotal,
                'tax' => $pv->tax,
                'total' => $pv->total,
                'sent_at' => null,
                'viewed_at' => null,
                'approved_at' => null,
                'rejected_at' => null,
            ]);

            $revision->update(['primary_version_id' => $newVersion->id]);

            foreach ($pv->lineItems as $item) {
                $attrs = $item->only([
                    'itemable_type', 'itemable_id', 'type', 'name', 'description', 'quantity',
                    'unit_price', 'discount', 'line_total', 'subtotal', 'total', 'position',
                    'asset_variant_id', 'asset_unit_id', 'inventory_unit_id',
                    'taxable', 'tax_rate', 'tax_amount',
                    'asset_options_fill_mode',
                ]);
                $attrs['parent_type'] = EstimateVersion::class;
                $attrs['parent_id'] = $newVersion->id;
                $attrs['customer_asset_options_completed_at'] = null;
                $attrs['customer_asset_options_signer_name'] = null;
                $attrs['customer_asset_options_signer_ip'] = null;
                $attrs['source_transaction_line_item_id'] = null;

                $newItem = EstimateLineItem::create($attrs);

                foreach ($item->addons as $addon) {
                    $addonData = $addon->only([
                        'addon_id', 'name', 'price', 'quantity', 'notes', 'metadata',
                        'taxable', 'tax_rate', 'tax_amount',
                    ]);
                    $addonData['transaction_line_item_id'] = $newItem->id;
                    EstimateLineItemAddon::create($addonData);
                }

                foreach ($item->selectedAssetOptions ?? [] as $sel) {
                    EstimateSelectedOption::create([
                        'estimate_id' => $revision->id,
                        'transaction_line_item_id' => $newItem->id,
                        'option_id' => $sel->option_id,
                        'option_value_id' => $sel->option_value_id,
                        'option_name' => $sel->option_name,
                        'value_label' => $sel->value_label,
                        'cost' => $sel->cost,
                        'price' => $sel->price,
                        'taxable' => $sel->taxable ?? true,
                    ]);
                }
            }
        }

        // Update original status unless it was already declined or expired
        $keepStatuses = [EstimateStatus::Declined->id(), EstimateStatus::Expired->id()];
        if (! in_array((int) $original->status, $keepStatuses)) {
            $original->update(['status' => EstimateStatus::Cancelled->id()]);
        }

        return redirect()->route('estimates.show', $revision->id)
            ->with('success', 'Revision created. You are now viewing the new revision.');
    }

    public function createDeal(Request $request, CreateDealFromEstimate $createDeal, $id)
    {
        $estimate = RecordModel::findOrFail($id);

        $validated = $request->validate([
            'needs_contract' => ['sometimes', 'boolean'],
            'needs_delivery' => ['sometimes', 'boolean'],
        ]);

        $result = $createDeal($estimate, [
            'needs_contract' => (bool) ($validated['needs_contract'] ?? true),
            'needs_delivery' => (bool) ($validated['needs_delivery'] ?? false),
        ]);

        if (! $result['success'] || empty($result['transaction'])) {
            return back()
                ->withErrors(['error' => $result['message'] ?? 'Could not create deal.']);
        }

        $redirect = redirect()->route('transactions.show', $result['transaction']->id);

        if (! empty($result['already_existed'])) {
            return $redirect->with('info', $result['message'] ?? 'Deal already exists for this estimate.');
        }

        return $redirect->with('success', 'Deal created successfully.');
    }
}
