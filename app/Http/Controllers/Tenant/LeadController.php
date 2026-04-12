<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Domain\Customer\Actions\CreateCustomer as CreateCustomerAction;
use App\Domain\Customer\Models\Customer;
use App\Domain\Document\Models\Document;
use App\Domain\Lead\Actions\CreateLead;
use App\Domain\Lead\Actions\DeleteLead;
use App\Domain\Lead\Actions\UpdateLead;
use App\Domain\Lead\Models\Lead;
use App\Enums\Timezone;
use App\Http\Controllers\Concerns\HasImageSupport;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadController extends BaseController
{
    use AuthorizesRequests, HasImageSupport, HasSchemaSupport, ValidatesRequests;

    protected string $domainName = 'Lead';

    protected string $recordType = 'leads';

    protected string $recordTitle = 'Lead';

    protected Lead $recordModel;

    public function __construct(
        protected CreateLead $createLead,
        protected UpdateLead $updateLead,
        protected DeleteLead $deleteLead,
    ) {
        $this->middleware('auth');
        $this->recordModel = new Lead;
    }

    protected function getUnwrappedFieldsSchema(): array
    {
        $fieldsSchemaRaw = $this->getFieldsSchema();
        if (! is_array($fieldsSchemaRaw)) {
            return [];
        }

        $unwrapped = isset($fieldsSchemaRaw['fields']) ? $fieldsSchemaRaw['fields'] : $fieldsSchemaRaw;

        return is_array($unwrapped) ? $unwrapped : [];
    }

    protected function indexInertiaProps(Request $request, $records, $schema, array $fieldsSchema, $formSchema, array $enumOptions): array
    {
        return [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'pluralTitle' => Str::plural($this->recordTitle),
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ];
    }

    protected function inertiaUpdateSuccessRedirect(Request $request, int|string $id): RedirectResponse
    {
        return back()->with('success', $this->domainName.' updated successfully');
    }

    public function index(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                $selectFields = ['id'];

                if ($fieldDef['typeDomain'] === 'Qualification') {
                    $selectFields = ['id', 'sequence'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relationshipName] = Customer::eagerWithContactSelect();
                } else {
                    $selectFields[] = 'display_name';
                }

                if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                    $selectFields[] = $fieldDef['displayField'];
                }

                $selectFields = array_unique($selectFields);

                if (! isset($relationships[$relationshipName])) {
                    $relationships[$relationshipName] = function ($query) use ($selectFields) {
                        $query->select($selectFields);
                    };
                }
            }
        }

        $table = $this->recordModel->getTable();
        $query = Lead::query()->with($relationships)
            ->join('contacts', 'contacts.id', '=', $table.'.contact_id')
            ->select($table.'.*');

        $searchQuery = $request->get('search');
        if ($searchQuery && ! empty(trim($searchQuery))) {
            $searchTerm = '%'.strtolower(trim($searchQuery)).'%';
            $idTrim = trim((string) $searchQuery);
            $query->where(function ($q) use ($searchTerm, $idTrim, $table) {
                $q->whereRaw('LOWER(contacts.display_name) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(contacts.first_name) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(contacts.last_name) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(contacts.email) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(contacts.phone) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(contacts.mobile) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(contacts.company) LIKE ?', [$searchTerm]);
                if ($idTrim !== '' && ctype_digit($idTrim)) {
                    $q->orWhere($table.'.id', '=', (int) $idTrim);
                }
            });
        }

        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode($filtersParam), true);
                if (is_array($filters)) {
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Exception $e) {
                // ignore invalid filters
            }
        }

        $allowedSort = $this->sortableColumnsFromTableSchema($schema);
        $sp = $this->sortParamsFromRequest($request);
        $leadDbColumns = Schema::connection($this->recordModel->getConnectionName())->getColumnListing($table);
        $contactBackedSortKeys = ['display_name', 'email', 'phone', 'mobile', 'first_name', 'last_name', 'company', 'position', 'title', 'secondary_email', 'website'];

        $sortApplied = false;
        if ($sp['key'] !== null && isset($allowedSort[$sp['key']])) {
            if (in_array($sp['key'], $contactBackedSortKeys, true)) {
                $qualified = 'contacts.'.$sp['key'];
                $useLower = in_array($sp['key'], ['display_name', 'email', 'first_name', 'last_name', 'company', 'position', 'title', 'secondary_email', 'website'], true);
                if ($useLower) {
                    $query->orderByRaw('LOWER('.$qualified.') '.($sp['dir'] === 'desc' ? 'DESC' : 'ASC'));
                } else {
                    $query->orderBy($qualified, $sp['dir']);
                }
                $sortApplied = true;
            } elseif (in_array($sp['key'], $leadDbColumns, true)) {
                $query->orderBy($table.'.'.$sp['key'], $sp['dir']);
                $sortApplied = true;
            }
        }

        if (! $sortApplied) {
            $query->orderByRaw('LOWER(contacts.display_name) ASC');
        }

        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'records' => $records->items(),
                'schema' => $schema,
                'fieldsSchema' => $fieldsSchema,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ]);
        }

        return inertia(
            'Tenant/'.$this->domainName.'/Index',
            $this->indexInertiaProps($request, $records, $schema, $fieldsSchema, $formSchema, $enumOptions)
        );
    }

    public function create()
    {
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/'.$this->domainName.'/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
        ]);
    }

    public function store(Request $request, PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            $fieldsSchema = $this->getUnwrappedFieldsSchema();

            foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                if (isset($fieldDef['type']) && $fieldDef['type'] === 'image') {
                    if ($request->hasFile($fieldKey)) {
                        $file = $request->file($fieldKey);
                        $meta = $fieldDef['meta'] ?? [];
                        $directory = $meta['directory'] ?? ($this->domainName.'/'.$fieldKey);
                        $isPrivate = $meta['private'] ?? false;
                        $resizeWidth = $meta['max_width'] ?? null;
                        $crop = $meta['crop'] ?? false;

                        $result = $publicStorage->store(
                            file: $file,
                            directory: $directory,
                            resizeWidth: $resizeWidth,
                            existingFile: null,
                            crop: $crop,
                            deleteOld: false,
                            isPrivate: $isPrivate
                        );

                        $document = Document::create([
                            'display_name' => $result['display_name'],
                            'file' => $result['key'],
                            'file_extension' => $result['file_extension'],
                            'file_size' => $result['file_size'],
                            'created_by_id' => auth()->id(),
                            'updated_by_id' => auth()->id(),
                        ]);

                        $data[$fieldKey] = $document->id;
                    }
                }
            }

            $result = ($this->createLead)($data);

            if (! is_array($result)) {
                $result = [
                    'success' => true,
                    'record' => $result,
                ];
            }

            if ($result['success']) {
                if ($request->ajax() && ! $request->header('X-Inertia')) {
                    $relationships = $this->getRelationshipsToLoad($fieldsSchema);
                    foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                        if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                            $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                            $selectFields = ['id'];
                            if ($fieldDef['typeDomain'] === 'Qualification') {
                                $selectFields = ['id', 'sequence'];
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'sequence']);
                                };
                            } elseif ($fieldDef['typeDomain'] === 'Customer') {
                                $relationships[$relationshipName] = Customer::eagerWithContactSelect();
                            } else {
                                $selectFields[] = 'display_name';
                            }
                            if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                                $selectFields[] = $fieldDef['displayField'];
                            }
                            $selectFields = array_unique($selectFields);
                            if (! isset($relationships[$relationshipName])) {
                                $relationships[$relationshipName] = function ($query) use ($selectFields) {
                                    $query->select($selectFields);
                                };
                            }
                        }
                    }
                    $record = Lead::with($relationships)->find($result['record']->id);

                    return response()->json([
                        'success' => true,
                        'recordId' => $result['record']->id,
                        'record' => $record,
                        'message' => $this->domainName.' created successfully',
                    ]);
                }

                return redirect()
                    ->route($this->recordType.'.show', $result['record']->id)
                    ->with('success', $this->domainName.' created successfully')
                    ->with('recordId', $result['record']->id);
            }

            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $result['errors'] ?? [],
                    'message' => $result['message'] ?? 'Failed to create '.$this->recordTitle,
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', $result['message'] ?? 'Failed to create '.$this->recordTitle);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed',
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors($e->errors());
        }
    }

    public function show(Request $request, $id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                $selectFields = ['id', 'display_name'];
                if ($fieldDef['typeDomain'] === 'Qualification') {
                    $selectFields = ['id', 'sequence'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relationshipName] = Customer::eagerWithContactSelect();
                } elseif (! isset($relationships[$relationshipName])) {
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

        $relationships['contact'] = function ($query) {
            $query->with('addresses');
        };
        $relationships['scores'] = function ($query) {
            $query->orderByDesc('created_at');
        };
        $relationships['converted_customer'] = Customer::eagerWithContactSelect();

        $record = Lead::with($relationships)->findOrFail($id);

        $scores = $record->scores;
        $record->unsetRelation('scores');

        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

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
                'scores' => $scores,
                'scoreScorableType' => Lead::class,
            ]);
        }

        return inertia('Tenant/Lead/Show', [
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
            'scores' => $scores,
            'scoreScorableType' => Lead::class,
        ]);
    }

    public function edit($id)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                $selectFields = ['id'];
                if ($fieldDef['typeDomain'] === 'Qualification') {
                    $selectFields = ['id', 'sequence'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relationshipName] = Customer::eagerWithContactSelect();
                } else {
                    $selectFields[] = 'display_name';
                }
                if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                    $selectFields[] = $fieldDef['displayField'];
                }
                $selectFields = array_unique($selectFields);
                if (! isset($relationships[$relationshipName])) {
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

        $formGroups = $formSchema['form'] ?? $formSchema;
        $hasSpecsGroup = is_array($formGroups) && collect($formGroups)
            ->contains(fn ($g) => is_array($g) && ($g['type'] ?? null) === 'specs');

        if ($hasSpecsGroup) {
            $relationships['specValues'] = fn ($q) => $q->with('definition');
        }

        $relationships['contact'] = function ($query) {
            $query->with('addresses');
        };

        $record = Lead::with($relationships)->findOrFail($id);

        $availableSpecs = $hasSpecsGroup && isset($record->type)
            ? AvailableAssetSpecsCache::get((int) $record->type)
            : [];

        $enumOptions = $this->getEnumOptions();
        $account = \App\Models\AccountSettings::getCurrent();

        return inertia('Tenant/'.$this->domainName.'/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'imageUrls' => $this->getImageUrls($record, $fieldsSchema),
            'account' => $account,
            'timezones' => Timezone::options(),
            'availableSpecs' => $availableSpecs,
        ]);
    }

    public function update(Request $request, $id, PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            $fieldsSchema = $this->getUnwrappedFieldsSchema();

            foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                if (isset($fieldDef['type']) && $fieldDef['type'] === 'image') {
                    $currentRecord = Lead::find($id);
                    $existingDocumentId = $currentRecord ? $currentRecord->{$fieldKey} : null;

                    if ($request->hasFile($fieldKey)) {
                        $file = $request->file($fieldKey);
                        $meta = $fieldDef['meta'] ?? [];
                        $directory = $meta['directory'] ?? ($this->domainName.'/'.$fieldKey);
                        $isPrivate = $meta['private'] ?? false;
                        $resizeWidth = $meta['max_width'] ?? null;
                        $crop = $meta['crop'] ?? false;

                        $existingDocument = $existingDocumentId ? Document::find($existingDocumentId) : null;
                        $existingFileKey = $existingDocument ? $existingDocument->file : null;

                        $storageResult = $publicStorage->store(
                            file: $file,
                            directory: $directory,
                            resizeWidth: $resizeWidth,
                            existingFile: $existingFileKey,
                            crop: $crop,
                            deleteOld: true,
                            isPrivate: $isPrivate
                        );

                        $document = Document::create([
                            'display_name' => $storageResult['display_name'],
                            'file' => $storageResult['key'],
                            'file_extension' => $storageResult['file_extension'],
                            'file_size' => $storageResult['file_size'],
                            'created_by_id' => auth()->id(),
                            'updated_by_id' => auth()->id(),
                        ]);

                        if ($existingDocument) {
                            $existingDocument->delete();
                        }

                        $data[$fieldKey] = $document->id;
                    } elseif (isset($data[$fieldKey]) && $data[$fieldKey] == $existingDocumentId) {
                        unset($data[$fieldKey]);
                    }
                }
            }

            $result = ($this->updateLead)($id, $data);

            if ($result['success']) {
                if ($request->ajax() && ! $request->header('X-Inertia')) {
                    $relationships = $this->getRelationshipsToLoad($fieldsSchema);
                    foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                        if (isset($fieldDef['type']) && $fieldDef['type'] === 'record' && isset($fieldDef['typeDomain'])) {
                            $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
                            $selectFields = ['id'];
                            if ($fieldDef['typeDomain'] === 'Qualification') {
                                $selectFields = ['id', 'sequence'];
                                $relationships[$relationshipName] = function ($query) {
                                    $query->select(['id', 'sequence']);
                                };
                            } elseif ($fieldDef['typeDomain'] === 'Customer') {
                                $relationships[$relationshipName] = Customer::eagerWithContactSelect();
                            } else {
                                $selectFields[] = 'display_name';
                            }
                            if (isset($fieldDef['displayField']) && $fieldDef['displayField'] !== 'display_name') {
                                $selectFields[] = $fieldDef['displayField'];
                            }
                            $selectFields = array_unique($selectFields);
                            if (! isset($relationships[$relationshipName])) {
                                $relationships[$relationshipName] = function ($query) use ($selectFields) {
                                    $query->select($selectFields);
                                };
                            }
                        }
                    }
                    $record = Lead::with($relationships)->find($id);

                    return response()->json([
                        'success' => true,
                        'record' => $record,
                        'message' => $this->domainName.' updated successfully',
                    ]);
                }

                return $this->inertiaUpdateSuccessRedirect($request, $id);
            }

            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $result['errors'] ?? [],
                    'message' => $result['message'] ?? 'Failed to update '.$this->recordTitle,
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['general' => $result['message'] ?? 'Failed to update '.$this->recordTitle]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Validation failed',
                ], 422);
            }

            throw $e;
        }
    }

    public function destroy($id)
    {
        $result = ($this->deleteLead)($id);

        if ($result['success']) {
            return redirect()
                ->route($this->recordType.'.index')
                ->with('success', $this->domainName.' deleted successfully');
        }

        return back()
            ->with('error', $result['message'] ?? 'Failed to delete '.$this->recordTitle);
    }

    public function convert(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        if ($lead->converted) {
            return back()->with('error', 'Lead has already been converted.');
        }

        $customerData = [
            'contact_id' => $lead->contact_id,
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
            'status_id' => 1,
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
            'budget_min' => null,
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
            'inactive' => false,
            'converted_from_lead_id' => $lead->id,
        ];

        $customerAction = new CreateCustomerAction;
        $customerResult = $customerAction($customerData);

        if (! $customerResult['success']) {
            return back()->with('error', 'Failed to create customer: '.($customerResult['message'] ?? 'Unknown error'));
        }

        $customer = $customerResult['record'];

        $lead->update([
            'converted' => true,
            'status_id' => 4,
            'converted_customer_id' => $customer->id,
            'converted_at' => now(),
        ]);

        return back()->with([
            'success' => 'Lead successfully converted to customer.',
            'converted_customer_id' => $customer->id,
            'converted_customer_name' => $customer->display_name,
        ]);
    }
}
