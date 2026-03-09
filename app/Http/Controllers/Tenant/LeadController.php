<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\RecordController;
use App\Domain\Lead\Models\Lead as RecordModel;
use App\Domain\Lead\Actions\CreateLead as CreateAction;
use App\Domain\Lead\Actions\UpdateLead as UpdateAction;
use App\Domain\Lead\Actions\DeleteLead as DeleteAction;
use App\Domain\Customer\Actions\CreateCustomer as CreateCustomerAction;
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

        // Eager-load current scores and converted customer alongside standard relationships
        $relationships['currentScores'] = function ($query) {
            $query->orderByDesc('created_at');
        };
        $relationships['converted_customer'] = function ($query) {
            $query->select('id', 'display_name');
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

        // Map lead data to customer data
        $customerData = [
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'mobile' => $lead->mobile,
            'company' => $lead->company,
            'position' => $lead->position,
            'title' => $lead->title,
            'secondary_email' => $lead->secondary_email,
            'address_line_1' => $lead->address_line_1,
            'address_line_2' => $lead->address_line_2,
            'city' => $lead->city,
            'state' => $lead->state,
            'postal_code' => $lead->postal_code,
            'country' => $lead->country,
            'latitude' => $lead->latitude,
            'longitude' => $lead->longitude,
            'notes' => $lead->notes,
            'status_id' => 1, // Set to active status for new customers
            'source_id' => $lead->source_id,
            'priority_id' => $lead->priority_id,
            'assigned_user_id' => $lead->assigned_user_id,
            'last_contacted_at' => $lead->last_contacted_at,
            'next_followup_at' => $lead->next_followup_at,
            'lead_score' => $lead->lead_score,
            'campaign' => $lead->campaign,
            'medium' => $lead->medium,
            'source_details' => $lead->source_details,
            'referrer' => $lead->referrer,
            'preferred_contact_method' => $lead->preferred_contact_method,
            'preferred_contact_time' => $lead->preferred_contact_time,
            'purchase_timeline' => $lead->purchase_timeline,
            'budget_min' => null, // Lead has budget_range enum, customer has separate min/max
            'budget_max' => null,
            'interested_model' => $lead->interested_model,
            'has_trade_in' => $lead->has_trade_in,
            'trade_in_value' => $lead->trade_in_value,
            'marketing_opt_in' => $lead->marketing_opt_in,
            'utm_source' => $lead->utm_source,
            'utm_medium' => $lead->utm_medium,
            'utm_campaign' => $lead->utm_campaign,
            'utm_term' => $lead->utm_term,
            'utm_content' => $lead->utm_content,
            'website' => $lead->website,
            'linkedin' => $lead->linkedin,
            'facebook' => $lead->facebook,
            'inactive' => false, // New customers start as active
        ];

        // Create the customer
        $customerAction = new CreateCustomerAction();
        $customerResult = $customerAction($customerData);

        if (!$customerResult['success']) {
            return back()->with('error', 'Failed to create customer: ' . ($customerResult['message'] ?? 'Unknown error'));
        }

        $customer = $customerResult['record'];

        // Update the lead as converted
        $lead->update([
            'converted' => true,
            'status_id' => 4, // Assuming 4 is the converted status
            'converted_customer_id' => $customer->id,
            'converted_at' => now()
        ]);

        // Return with success and customer info for frontend navigation
        return back()->with([
            'success' => 'Lead successfully converted to customer.',
            'converted_customer_id' => $customer->id,
            'converted_customer_name' => $customer->display_name
        ]);
    }
}