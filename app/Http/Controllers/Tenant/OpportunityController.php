<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Customer\Models\Customer;
use App\Domain\Opportunity\Actions\CreateOpportunity as CreateAction;
use App\Domain\Opportunity\Actions\DeleteOpportunity as DeleteAction;
use App\Domain\Opportunity\Actions\UpdateOpportunity as UpdateAction;
use App\Domain\Opportunity\Models\Opportunity as RecordModel;
use App\Domain\Qualification\Models\Qualification;
use App\Enums\Entity\BudgetRange;
use App\Enums\Entity\PurchaseTimeline;
use App\Enums\Timezone;
use Illuminate\Http\Request;

class OpportunityController extends RecordController
{
    protected $recordType = 'Opportunity';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'opportunities',
            'Opportunity',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }

    public function create()
    {
        $request = request();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $initialData = [];

        if ($request->query('from') === 'qualification' && $request->query('id')) {
            $qualification = Qualification::with(['lead.converted_customer'])->find($request->query('id'));

            if ($qualification) {
                $user = auth()->user();

                $initialData = [
                    'qualification_id' => $qualification->id,
                    'qualification' => ['id' => $qualification->id, 'display_name' => $qualification->display_name],
                    'user_id' => $user->id,
                    'user' => ['id' => $user->id, 'display_name' => $user->display_name ?? $user->name ?? ''],
                ];

                if ($qualification->lead && $qualification->lead->converted_customer_id) {
                    $customer = $qualification->lead->converted_customer;
                    $initialData['customer_id'] = $qualification->lead->converted_customer_id;
                    $initialData['customer'] = ['id' => $customer->id, 'display_name' => $customer->display_name];
                }
            }
        }

        return inertia('Tenant/Opportunity/Create', [
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'initialData' => $initialData,
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
                if (isset($relationships[$relName])) {
                    continue;
                }

                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    $relationships[$relName] = function ($q) {
                        $q->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                            ->with(['asset' => fn ($q2) => $q2->select(['id', 'display_name'])]);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Qualification') {
                    // handled below
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relName] = Customer::eagerWithContactSelect();
                } else {
                    $relationships[$relName] = fn ($q) => $q->select(['id', 'display_name']);
                }
            }
        }

        // Load full qualification with product requirements + nested desired_brand
        $relationships['qualification'] = fn ($q) => $q->select('*')
            ->with(['desired_brand' => fn ($q2) => $q2->select(['id', 'display_name'])]);

        // Load inventory items (Parts & Accessories) and assets with pivot data
        $relationships['inventoryItems'] = fn ($q) => $q->withPivot('quantity', 'unit_price', 'estimated_cost', 'notes');
        $relationships['assets'] = fn ($q) => $q->with('make:id,display_name')
            ->withPivot('quantity', 'unit_price', 'estimated_cost', 'notes', 'asset_variant_id');

        $record = $this->recordModel->with($relationships)->findOrFail($id);
        RecordModel::hydratePivotAssetVariants($record->assets);

        return inertia('Tenant/Opportunity/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'qualificationEnumOptions' => [
                'budget_range' => BudgetRange::options(),
                'purchase_timeline' => PurchaseTimeline::options(),
            ],
        ]);
    }

    public function edit($id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                if (isset($relationships[$relName])) {
                    continue;
                }

                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    $relationships[$relName] = function ($q) {
                        $q->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                            ->with(['asset' => fn ($q2) => $q2->select(['id', 'display_name'])]);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Qualification') {
                    $relationships[$relName] = fn ($q) => $q->select(['id', 'sequence']);
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relName] = Customer::eagerWithContactSelect();
                } else {
                    $relationships[$relName] = fn ($q) => $q->select(['id', 'display_name']);
                }
            }
        }

        // Load inventory items (Parts & Accessories) and assets with pivot data
        $relationships['inventoryItems'] = fn ($q) => $q->withPivot('quantity', 'unit_price', 'estimated_cost', 'notes');
        $relationships['assets'] = fn ($q) => $q->with('make:id,display_name')
            ->withPivot('quantity', 'unit_price', 'estimated_cost', 'notes', 'asset_variant_id');

        $record = $this->recordModel->with($relationships)->findOrFail($id);
        RecordModel::hydratePivotAssetVariants($record->assets);

        return inertia('Tenant/Opportunity/Edit', [
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
}
