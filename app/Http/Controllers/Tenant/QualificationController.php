<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Qualification\Models\Qualification as RecordModel;
use App\Domain\Qualification\Actions\CreateQualification as CreateAction;
use App\Domain\Qualification\Actions\UpdateQualification as UpdateAction;
use App\Domain\Qualification\Actions\DeleteQualification as DeleteAction;
use App\Enums\Timezone;
use App\Enums\Opportunity\Stage as OpportunityStage;
use App\Enums\Opportunity\Status as OpportunityStatus;
use Illuminate\Http\Request;

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
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType
        );
    }

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                if (!isset($relationships[$relationshipName])) {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'display_name']);
                    };
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

        // Eager-load lead with its converted_customer relationship
        $relationships['lead'] = function ($query) {
            $query->with(['converted_customer' => function ($q) {
                $q->select('id', 'display_name');
            }])->select('id', 'display_name', 'first_name', 'last_name', 'converted', 'converted_customer_id', 'converted_at');
        };

        $record = RecordModel::with($relationships)->findOrFail($id);

        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        $opportunityStageOptions  = OpportunityStage::options();
        $opportunityStatusOptions = OpportunityStatus::options();

        // Build leadData for the frontend conversion flow
        $leadData = null;
        if ($record->lead) {
            $leadData = [
                'id'                    => $record->lead->id,
                'converted'             => (bool) $record->lead->converted,
                'converted_customer_id' => $record->lead->converted_customer_id,
                'display_name'          => trim(($record->lead->first_name ?? '') . ' ' . ($record->lead->last_name ?? '')),
            ];
        }

        if ($request->ajax() && !$request->header('X-Inertia')) {
            return response()->json([
                'record'                   => $record,
                'recordType'               => $this->recordType,
                'recordTitle'              => $this->recordTitle,
                'domainName'               => $this->domainName,
                'formSchema'               => $formSchema,
                'fieldsSchema'             => $fieldsSchema,
                'enumOptions'              => $enumOptions,
                'imageUrls'                => $this->getImageUrls($record, $fieldsSchema),
                'account'                  => $account,
                'timezones'                => Timezone::options(),
                'leadData'                 => $leadData,
                'opportunityStageOptions'  => $opportunityStageOptions,
                'opportunityStatusOptions' => $opportunityStatusOptions,
            ]);
        }

        return inertia('Tenant/Qualification/Show', [
            'record'                   => $record,
            'recordType'               => $this->recordType,
            'recordTitle'              => $this->recordTitle,
            'domainName'               => $this->domainName,
            'formSchema'               => $formSchema,
            'fieldsSchema'             => $fieldsSchema,
            'enumOptions'              => $enumOptions,
            'imageUrls'                => $this->getImageUrls($record, $fieldsSchema),
            'account'                  => $account,
            'timezones'                => Timezone::options(),
            'leadData'                 => $leadData,
            'opportunityStageOptions'  => $opportunityStageOptions,
            'opportunityStatusOptions' => $opportunityStatusOptions,
        ]);
    }
}
