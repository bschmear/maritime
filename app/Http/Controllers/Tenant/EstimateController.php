<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Asset\Models\Asset;
use App\Domain\AssetOption\Models\EstimateSelectedOption;
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
use App\Services\SMS\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
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
            if (($li->itemable_type ?? '') !== \App\Domain\Asset\Models\Asset::class) {
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
        $account = \App\Models\AccountSettings::getCurrent();

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
                $user = auth()->user();

                $initialData = [
                    'opportunity_id' => $opportunity->id,
                    'opportunity' => ['id' => $opportunity->id, 'display_name' => $opportunity->display_name],
                    'user_id' => $user->id,
                    'user' => ['id' => $user->id, 'display_name' => $user->display_name ?? $user->name ?? ''],
                ];

                if ($opportunity->customer_id) {
                    $initialData['customer_id'] = $opportunity->customer_id;
                    $initialData['customer'] = ['id' => $opportunity->customer->id, 'display_name' => $opportunity->customer->display_name];

                    // Populate contact_id so the contact-first picker pre-fills.
                    $contactId = $opportunity->customer->contact_id;
                    if ($contactId) {
                        $contact = $opportunity->customer->contact ?? \App\Domain\Contact\Models\Contact::find($contactId);
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
        $account = \App\Models\AccountSettings::getCurrent();

        $relationships = $this->buildEstimateRecordEagerRelationships($fieldsSchema);

        $relationships['primaryVersion'] = fn ($q) => $q->with([
            'lineItems' => fn ($q2) => $q2->with([
                'addons.addon:id,name,default_price',
                'itemable',
                'assetVariant',
                'assetUnit',
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
            ->select(['id', 'estimate_id', 'transaction_line_item_id', 'option_name', 'value_label', 'price'])
            ->orderBy('id');

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
        $account = \App\Models\AccountSettings::getCurrent();

        $relationships = $this->buildEstimateRecordEagerRelationships($fieldsSchema);

        $relationships['primaryVersion'] = fn ($q) => $q->with([
            'lineItems' => fn ($q2) => $q2->with([
                'addons.addon:id,name,default_price',
                'itemable',
                'assetVariant',
                'assetUnit',
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

    public function sendApprovalRequest(Request $request, $id, SmsService $smsService)
    {
        $validated = $request->validate([
            'delivery' => 'required|string|in:email,email_sms',
        ]);

        $estimate = RecordModel::with([
            'customer' => Customer::eagerWithContactSelect(['email', 'phone', 'mobile']),
            'primaryVersion',
            'salesperson',
        ])->findOrFail($id);

        $wasPendingApproval = (int) $estimate->status === EstimateStatus::PendingApproval->id();

        $account = AccountSettings::getCurrent();
        $sandbox = $account->smsSandboxMode();
        $customerEmail = $estimate->customer?->email;
        $authEmail = $request->user()?->email;

        if ($sandbox) {
            if (! $authEmail) {
                return back()->withErrors(['error' => 'Sandbox mode sends the approval email to you, but your account has no email address on file.']);
            }
            $approvalRecipientEmail = $authEmail;
        } else {
            if (! $customerEmail) {
                return back()->withErrors(['error' => 'This estimate has no customer email address.']);
            }
            $approvalRecipientEmail = $customerEmail;
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

        try {
            Mail::to($approvalRecipientEmail)->send(new EstimateApprovalRequest($estimate, $account, $reviewUrl));
        } catch (\Exception $e) {
            \Log::error('Failed to send estimate approval request email', [
                'estimate_id' => $estimate->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to send email. Please try again.']);
        }

        $emailTarget = $sandbox
            ? "{$approvalRecipientEmail} (sandbox — you)"
            : $approvalRecipientEmail;

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
    public function sendBoatOptionsInvite(Request $request, $id)
    {
        $estimate = RecordModel::with(['customer', 'primaryVersion.lineItems', 'salesperson'])->findOrFail($id);

        $customerEmail = $estimate->customer?->email;
        if (! $customerEmail) {
            return back()->withErrors(['error' => 'This estimate has no customer email address.']);
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

        try {
            Mail::to($customerEmail)->send(new EstimateBoatOptionsInvite($estimate, $account, $lines));
        } catch (\Exception $e) {
            \Log::error('Failed to send boat options invite email', [
                'estimate_id' => $estimate->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to send email. Please try again.']);
        }

        return back()->with('success', 'Boat options link(s) sent to '.$customerEmail.'.');
    }

    public function createRevision(Request $request, $id)
    {
        $original = RecordModel::with([
            'primaryVersion.lineItems.addons',
        ])->findOrFail($id);

        // Already has a revision — navigate to it
        $existing = RecordModel::where('revised_from_id', $original->id)->first();
        if ($existing) {
            return redirect()->route('estimates.show', $existing->id)
                ->with('info', 'A revision already exists for this estimate.');
        }

        // Create the new revision estimate
        $revision = RecordModel::create([
            'customer_id' => $original->customer_id,
            'user_id' => $original->user_id,
            'opportunity_id' => $original->opportunity_id,
            'status' => EstimateStatus::Draft->id(),
            'tax_rate' => $original->tax_rate,
            'notes' => $original->notes,
            'terms' => $original->terms,
            'issue_date' => now()->toDateString(),
            'revised_from_id' => $original->id,
        ]);

        // Copy the primary version + all line items + addons
        if ($original->primaryVersion) {
            $newVersion = \App\Domain\Estimate\Models\EstimateVersion::create([
                'estimate_id' => $revision->id,
                'version' => 1,
                'is_primary' => true,
                'tax_rate' => $original->primaryVersion->tax_rate,
                'subtotal' => $original->primaryVersion->subtotal,
                'tax' => $original->primaryVersion->tax,
                'total' => $original->primaryVersion->total,
            ]);

            $revision->update(['primary_version_id' => $newVersion->id]);

            foreach ($original->primaryVersion->lineItems as $item) {
                $itemData = $item->toArray();
                unset(
                    $itemData['id'],
                    $itemData['parent_type'],
                    $itemData['parent_id'],
                    $itemData['created_at'],
                    $itemData['updated_at'],
                );
                $itemData['parent_type'] = \App\Domain\Estimate\Models\EstimateVersion::class;
                $itemData['parent_id'] = $newVersion->id;

                $newItem = \App\Domain\Estimate\Models\EstimateLineItem::create($itemData);

                foreach ($item->addons as $addon) {
                    $addonData = $addon->toArray();
                    unset($addonData['id'], $addonData['transaction_line_item_id'], $addonData['created_at'], $addonData['updated_at']);
                    $addonData['transaction_line_item_id'] = $newItem->id;
                    \App\Domain\Estimate\Models\EstimateLineItemAddon::create($addonData);
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

        $result = $createDeal($estimate);

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
