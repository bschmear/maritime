<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Lead\Models\Lead as RecordModel;
use App\Domain\Lead\Actions\CreateLead as CreateAction;
use App\Domain\Lead\Actions\UpdateLead as UpdateAction;
use App\Domain\Lead\Actions\DeleteLead as DeleteAction;
use App\Enums\Timezone;
use Illuminate\Http\Request;

class LeadController extends RecordController
{
    protected $recordType = 'Lead';
    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'leads',
            'Lead',
            new RecordModel(),
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
            $this->recordType // Domain name for schema lookup
        );
    }

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                $selectFields = ['id', 'display_name'];
                if (!isset($relationships[$relationshipName])) {
                    $relationships[$relationshipName] = function ($query) use ($selectFields) {
                        $query->select($selectFields);
                    };
                }
            }
        }

        $formSchema = $this->getFormSchema();

        if (isset($formSchema['sublists']) && is_array($formSchema['sublists'])) {
            foreach ($formSchema['sublists'] as $sublist) {
                if (isset($sublist['modelRelationship'])) {
                    $relationships[$sublist['modelRelationship']] = function ($query) {
                        $query->select('*');
                    };
                }
            }
        }

        // Eager-load current scores alongside standard relationships
        $relationships['currentScores'] = function ($query) {
            $query->orderByDesc('created_at');
        };

        $record = RecordModel::with($relationships)->findOrFail($id);

        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        if ($request->ajax() && !$request->header('X-Inertia')) {
            return response()->json([
                'record'      => $record,
                'recordType'  => $this->recordType,
                'recordTitle' => $this->recordTitle,
                'domainName'  => $this->domainName,
                'formSchema'  => $formSchema,
                'fieldsSchema'=> $fieldsSchema,
                'enumOptions' => $enumOptions,
                'imageUrls'   => $this->getImageUrls($record, $fieldsSchema),
                'account'     => $account,
                'timezones'   => Timezone::options(),
                'scores'      => $record->currentScores,
            ]);
        }

        return inertia('Tenant/Lead/Show', [
            'record'      => $record,
            'recordType'  => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName'  => $this->domainName,
            'formSchema'  => $formSchema,
            'fieldsSchema'=> $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls'   => $this->getImageUrls($record, $fieldsSchema),
            'account'     => $account,
            'timezones'   => Timezone::options(),
            'scores'      => $record->currentScores,
        ]);
    }

    public function convert(Request $request, $id)
    {
        $lead = RecordModel::findOrFail($id);

        if ($lead->converted) {
            return back()->with('error', 'Lead has already been converted.');
        }

        $lead->update([
            'converted'    => true,
            'converted_at' => now(),
        ]);

        return back()->with('success', 'Lead successfully converted.');
    }
}