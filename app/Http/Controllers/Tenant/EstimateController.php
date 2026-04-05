<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Estimate\Actions\CreateDealFromEstimate;
use App\Domain\Estimate\Actions\CreateEstimate as CreateAction;
use App\Domain\Estimate\Actions\DeleteEstimate as DeleteAction;
use App\Domain\Estimate\Actions\UpdateEstimate as UpdateAction;
use App\Domain\Estimate\Models\Estimate as RecordModel;
use App\Domain\Opportunity\Models\Opportunity;
use App\Enums\Estimate\EstimateStatus;
use App\Enums\Timezone;
use App\Mail\EstimateApprovalRequest;
use App\Models\AccountSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EstimateController extends RecordController
{
    protected $recordType = 'Estimate';

    protected $table = null;

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

    public function store(Request $request, $publicStorage = null)
    {
        $data = $request->all();

        $result = $this->createAction->__invoke($data);

        if ($result['success']) {
            return redirect()->route('estimates.show', $result['record']->id)
                ->with('success', 'Estimate created successfully.');
        }

        return back()
            ->withErrors(['error' => $result['message'] ?? 'Failed to create estimate'])
            ->withInput();
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
                'assets' => fn ($q) => $q->with('make:id,display_name')->withPivot('quantity', 'unit_price', 'estimated_cost', 'notes', 'asset_variant_id'),
                'inventoryItems' => fn ($q) => $q->withPivot('quantity', 'unit_price', 'estimated_cost', 'notes'),
            ])->find($request->query('id'));

            if ($opportunity) {
                Opportunity::hydratePivotAssetVariants($opportunity->assets);
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
                }

                $opportunityLineItems = [
                    'assets' => $opportunity->assets ?? [],
                    'inventoryItems' => $opportunity->inventoryItems ?? [],
                ];
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
        ]);
    }

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                if (! isset($relationships[$relName])) {
                    $relationships[$relName] = fn ($q) => $q->select(['id', 'display_name']);
                }
            }
        }

        $relationships['primaryVersion'] = fn ($q) => $q->with([
            'lineItems' => fn ($q2) => $q2->with([
                'addons.addon:id,name,default_price',
                'itemable',
                'assetVariant',
            ]),
        ]);
        $relationships['revision'] = fn ($q) => $q->select('id', 'sequence', 'revised_from_id');
        $relationships['revisedFrom'] = fn ($q) => $q->select('id', 'sequence');

        $record = $this->recordModel->with($relationships)->findOrFail($id);

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

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                if (! isset($relationships[$relName])) {
                    $relationships[$relName] = fn ($q) => $q->select(['id', 'display_name']);
                }
            }
        }

        $relationships['primaryVersion'] = fn ($q) => $q->with([
            'lineItems' => fn ($q2) => $q2->with([
                'addons.addon:id,name,default_price',
                'itemable',
                'assetVariant',
            ]),
        ]);

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

        return back()
            ->withErrors(['error' => $result['message'] ?? 'Failed to update estimate'])
            ->withInput();
    }

    public function sendApprovalRequest(Request $request, $id)
    {
        $estimate = RecordModel::with(['customer', 'primaryVersion'])->findOrFail($id);

        $customerEmail = $estimate->customer?->email;

        if (! $customerEmail) {
            return back()->withErrors(['error' => 'This estimate has no customer email address.']);
        }

        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;

        if (! $domain) {
            return back()->withErrors(['error' => 'Unable to resolve tenant domain.']);
        }

        $reviewUrl = "https://{$domain}/estimates/{$estimate->uuid}/review";
        $account = AccountSettings::getCurrent();

        try {
            Mail::to($customerEmail)->send(new EstimateApprovalRequest($estimate, $account, $reviewUrl));
        } catch (\Exception $e) {
            \Log::error('Failed to send estimate approval request email', [
                'estimate_id' => $estimate->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to send email. Please try again.']);
        }

        $estimate->update([
            'sent_at' => now(),
            'status' => EstimateStatus::PendingApproval->id(),
        ]);

        return back()->with('success', "Estimate sent to {$customerEmail} for approval.");
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
                unset($itemData['id'], $itemData['estimate_version_id'], $itemData['created_at'], $itemData['updated_at']);
                $itemData['estimate_version_id'] = $newVersion->id;

                $newItem = \App\Domain\Estimate\Models\EstimateLineItem::create($itemData);

                foreach ($item->addons as $addon) {
                    $addonData = $addon->toArray();
                    unset($addonData['id'], $addonData['estimate_line_item_id'], $addonData['created_at'], $addonData['updated_at']);
                    $addonData['estimate_line_item_id'] = $newItem->id;
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
