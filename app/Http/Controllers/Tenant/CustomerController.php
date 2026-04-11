<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Actions\CreateCustomer as CreateAction;
use App\Domain\Customer\Actions\DeleteCustomer as DeleteAction;
use App\Domain\Customer\Actions\UpdateCustomer as UpdateAction;
use App\Domain\Customer\Models\Customer as RecordModel;
use App\Enums\Timezone;
use App\Models\AccountSettings;
use Illuminate\Http\Request;

class CustomerController extends RecordController
{
    protected $recordType = 'Customer';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'customers',
            'Customer',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'Customer',
        );
    }

    public function create()
    {
        $request = request();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

        $initialData = [];
        $contactId = $request->query('contact_id');
        if ($contactId !== null && $contactId !== '' && ctype_digit((string) $contactId)) {
            $contact = Contact::query()->with('primaryAddress')->find((int) $contactId);
            if ($contact !== null) {
                $initialData['contact_id'] = $contact->id;
                $row = $contact->toArray();
                foreach (RecordModel::contactAttributeKeys() as $key) {
                    if (! array_key_exists($key, $row)) {
                        continue;
                    }
                    $v = $row[$key];
                    if ($v === null || $v === '') {
                        continue;
                    }
                    $initialData[$key] = $v;
                }
                $primary = $contact->primaryAddress;
                if ($primary !== null) {
                    $addr = $primary->toArray();
                    foreach (RecordModel::addressAttributeKeys() as $key) {
                        if (! array_key_exists($key, $addr)) {
                            continue;
                        }
                        $v = $addr[$key];
                        if ($v === null || $v === '') {
                            continue;
                        }
                        $initialData[$key] = $v;
                    }
                }

                foreach (['website', 'linkedin', 'facebook'] as $socialKey) {
                    $v = $contact->{$socialKey} ?? null;
                    if ($v !== null && $v !== '') {
                        $initialData[$socialKey] = $v;
                    }
                }
            }
        }

        return inertia('Tenant/'.$this->domainName.'/Create', [
            'recordType' => $this->recordType,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'account' => $account,
            'timezones' => Timezone::options(),
            'initialData' => $initialData,
        ]);
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

                if ($fieldDef['typeDomain'] === 'AssetUnit') {
                    $selectFields = ['id', 'serial_number', 'hin', 'sku', 'asset_id'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'serial_number', 'hin', 'sku', 'asset_id'])
                            ->with(['asset' => function ($q) {
                                $q->select(['id', 'display_name']);
                            }]);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Qualification') {
                    $selectFields = ['id', 'sequence'];
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'sequence']);
                    };
                } elseif ($fieldDef['typeDomain'] === 'Customer') {
                    $relationships[$relationshipName] = function ($query) {
                        $query->select(['id', 'contact_id'])
                            ->with(['contact' => function ($q) {
                                $q->select(['id', 'display_name', 'first_name', 'last_name']);
                            }]);
                    };
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
        $query = RecordModel::query()->with($relationships)
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
                //
            }
        }

        $query->orderByRaw('LOWER(contacts.display_name) ASC');

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
}
