<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Estimate\Models\Estimate as RecordModel;
use App\Domain\Estimate\Actions\CreateEstimate as CreateAction;
use App\Domain\Estimate\Actions\UpdateEstimate as UpdateAction;
use App\Domain\Estimate\Actions\DeleteEstimate as DeleteAction;
use App\Domain\Opportunity\Models\Opportunity;
use App\Enums\Timezone;
use Illuminate\Http\Request;

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
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
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
        $request      = request();
        $formSchema   = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions  = $this->getEnumOptions();
        $account      = \App\Models\AccountSettings::getCurrent();

        $initialData = [];
        $opportunityLineItems = null;

        if ($request->query('from') === 'opportunity' && $request->query('id')) {
            $opportunity = Opportunity::with([
                'customer',
                'user',
                'assets' => fn($q) => $q->with('make:id,display_name')->withPivot('quantity', 'unit_price', 'estimated_cost', 'notes'),
                'inventoryItems' => fn($q) => $q->withPivot('quantity', 'unit_price', 'estimated_cost', 'notes')
            ])->find($request->query('id'));

            if ($opportunity) {
                $user = auth()->user();

                $initialData = [
                    'opportunity_id' => $opportunity->id,
                    'opportunity'    => ['id' => $opportunity->id, 'display_name' => $opportunity->display_name],
                    'user_id'        => $user->id,
                    'user'           => ['id' => $user->id, 'display_name' => $user->display_name ?? $user->name ?? ''],
                ];

                if ($opportunity->customer_id) {
                    $initialData['customer_id'] = $opportunity->customer_id;
                    $initialData['customer']    = ['id' => $opportunity->customer->id, 'display_name' => $opportunity->customer->display_name];
                }

                $opportunityLineItems = [
                    'assets' => $opportunity->assets ?? [],
                    'inventoryItems' => $opportunity->inventoryItems ?? [],
                ];
            }
        }

        return inertia('Tenant/Estimate/Create', [
            'recordType'           => $this->recordType,
            'recordTitle'          => $this->recordTitle,
            'domainName'           => $this->domainName,
            'formSchema'           => $formSchema,
            'fieldsSchema'         => $fieldsSchema,
            'enumOptions'          => $enumOptions,
            'account'              => $account,
            'timezones'            => Timezone::options(),
            'initialData'          => $initialData,
            'opportunityLineItems' => $opportunityLineItems,
        ]);
    }

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema   = $this->getFormSchema();
        $enumOptions  = $this->getEnumOptions();
        $account      = \App\Models\AccountSettings::getCurrent();

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                if (!isset($relationships[$relName])) {
                    $relationships[$relName] = fn($q) => $q->select(['id', 'display_name']);
                }
            }
        }

        $relationships['primaryVersion'] = fn($q) => $q->with([
            'lineItems' => fn($q2) => $q2->with([
                'addons.addon:id,name,default_price',
                'itemable'
            ])
        ]);

        $record = $this->recordModel->with($relationships)->findOrFail($id);

        return inertia('Tenant/Estimate/Show', [
            'record'       => $record,
            'recordType'   => $this->recordType,
            'recordTitle'  => $this->recordTitle,
            'domainName'   => $this->domainName,
            'formSchema'   => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions'  => $enumOptions,
            'account'      => $account,
            'timezones'    => Timezone::options(),
        ]);
    }

    public function edit($id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema   = $this->getFormSchema();
        $enumOptions  = $this->getEnumOptions();
        $account      = \App\Models\AccountSettings::getCurrent();

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                if (!isset($relationships[$relName])) {
                    $relationships[$relName] = fn($q) => $q->select(['id', 'display_name']);
                }
            }
        }

        $relationships['primaryVersion'] = fn($q) => $q->with([
            'lineItems' => fn($q2) => $q2->with([
                'addons.addon:id,name,default_price',
                'itemable'
            ])
        ]);

        $record = $this->recordModel->with($relationships)->findOrFail($id);

        return inertia('Tenant/Estimate/Edit', [
            'record'       => $record,
            'recordType'   => $this->recordType,
            'recordTitle'  => $this->recordTitle,
            'domainName'   => $this->domainName,
            'formSchema'   => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions'  => $enumOptions,
            'account'      => $account,
            'timezones'    => Timezone::options(),
            'initialData'  => [],
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

}