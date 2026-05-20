<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;
use App\Domain\Qualification\Actions\CreateQualification as CreateAction;
use App\Domain\Qualification\Actions\DeleteQualification as DeleteAction;
use App\Domain\Qualification\Actions\UpdateQualification as UpdateAction;
use App\Domain\Qualification\Models\Qualification as RecordModel;
use App\Enums\Opportunity\Stage as OpportunityStage;
use App\Enums\Opportunity\Status as OpportunityStatus;
use App\Enums\Timezone;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;

class QualificationController extends RecordController
{
    protected $recordType = 'Qualification';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'qualifications',
            'Qualification',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType
        );
    }

    /**
     * Dedicated create page (schema-driven form), same pattern as estimates.
     */
    public function create()
    {
        $request = request();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $initialData = [];
        $leadId = $request->query('lead_id');
        if ($leadId !== null && $leadId !== '') {
            $lead = Lead::query()
                ->select(['id', 'contact_id', 'budget_range', 'purchase_timeline'])
                ->with([
                    'contact' => fn ($q) => $q->select(['id', 'display_name', 'first_name', 'last_name']),
                ])
                ->find((int) $leadId);

            if ($lead) {
                $initialData['lead_id'] = $lead->id;
                $initialData['lead'] = [
                    'id' => $lead->id,
                    'display_name' => $lead->display_name
                        ?? trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')),
                ];
                if ($lead->budget_range !== null) {
                    $initialData['budget_range'] = $lead->budget_range;
                }
                if ($lead->purchase_timeline !== null && $lead->purchase_timeline !== '') {
                    $initialData['purchase_timeline'] = $lead->purchase_timeline;
                }
            }
        }

        $user = $request->user();
        if ($user) {
            $initialData['user_id'] = $user->id;
            $initialData['user'] = [
                'id' => $user->id,
                'display_name' => $user->display_name ?? $user->name ?? '',
            ];
        }

        return inertia('Tenant/Qualification/Create', [
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

    public function store(Request $request, PublicStorage $publicStorage)
    {
        $data = $request->all();
        $result = $this->createAction->__invoke($data);

        if ($result['success']) {
            return redirect()->route('qualifications.show', $result['record']->id)
                ->with('success', 'Qualification created successfully.');
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

    /**
     * Dedicated edit page (schema-driven form).
     */
    public function edit($id)
    {
        $record = RecordModel::query()
            ->with([
                'lead' => fn ($q) => $q->select(['id', 'contact_id'])->with([
                    'contact' => fn ($c) => $c->select(['id', 'display_name', 'first_name', 'last_name']),
                ]),
                'user' => fn ($q) => $q->select(['id', 'display_name', 'first_name', 'last_name', 'email']),
                'desired_brand' => fn ($q) => $q->select(['id', 'display_name']),
                'opportunities' => fn ($q) => $q->select(['id', 'sequence', 'qualification_id']),
                'createdBy' => fn ($q) => $q->select(['id', 'display_name', 'first_name', 'last_name']),
            ])
            ->findOrFail($id);

        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/Qualification/Edit', [
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
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
        ]);
    }

    public function update(Request $request, $id, PublicStorage $publicStorage)
    {
        $data = $request->all();
        $result = $this->updateAction->__invoke((int) $id, $data);

        if ($result['success']) {
            return redirect()->route('qualifications.show', (int) $id)
                ->with('success', 'Qualification updated successfully.');
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

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                if (! isset($relationships[$relationshipName])) {
                    if (($fieldDef['typeDomain'] ?? '') === 'Customer') {
                        $relationships[$relationshipName] = Customer::eagerWithContactSelect();
                    } elseif (in_array(($fieldDef['typeDomain'] ?? ''), ['Lead', 'LeadProfile'], true)) {
                        $relationships[$relationshipName] = function ($query) {
                            $query->select(['id', 'contact_id'])
                                ->with(['contact' => function ($q) {
                                    $q->select(['id', 'display_name', 'first_name', 'last_name']);
                                }]);
                        };
                    } else {
                        $relationships[$relationshipName] = function ($query) {
                            $query->select(['id', 'display_name']);
                        };
                    }
                }
            }
        }

        $formSchema = $this->getFormSchema();

        // Load sublist relationships the same way RecordController does
        if (isset($formSchema['sublists']) && is_array($formSchema['sublists'])) {
            foreach ($formSchema['sublists'] as $sublist) {
                if (isset($sublist['modelRelationship'])) {
                    $relationships[$sublist['modelRelationship']] = function ($query) {
                        $query->select('*');
                    };
                }
            }
        }

        // Lead rows are lead_profiles; names live on contacts (accessors need contact loaded).
        $relationships['lead'] = function ($query) {
            $query->with([
                'contact' => function ($q) {
                    $q->select('id', 'display_name', 'first_name', 'last_name');
                },
                'converted_customer' => Customer::eagerWithContactSelect(),
            ]);
        };

        $record = RecordModel::with($relationships)->findOrFail($id);

        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $opportunityStageOptions = OpportunityStage::options();
        $opportunityStatusOptions = OpportunityStatus::options();

        // Build leadData for the frontend conversion flow
        $leadData = null;
        if ($record->lead) {
            $leadData = [
                'id' => $record->lead->id,
                'converted' => (bool) $record->lead->converted,
                'converted_customer_id' => $record->lead->converted_customer_id,
                'display_name' => $record->lead->display_name
                    ?? trim(($record->lead->first_name ?? '').' '.($record->lead->last_name ?? '')),
            ];
        }

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'record' => $record,
                'recordType' => $this->recordType,
                'recordTitle' => $this->recordTitle,
                'domainName' => $this->domainName,
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
                'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
                'account' => $account,
                'timezones' => Timezone::options(),
                'leadData' => $leadData,
                'opportunityStageOptions' => $opportunityStageOptions,
                'opportunityStatusOptions' => $opportunityStatusOptions,
            ]);
        }

        return inertia('Tenant/Qualification/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
            'account' => $account,
            'timezones' => Timezone::options(),
            'leadData' => $leadData,
            'opportunityStageOptions' => $opportunityStageOptions,
            'opportunityStatusOptions' => $opportunityStatusOptions,
        ]);
    }
}
