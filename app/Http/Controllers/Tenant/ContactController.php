<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use App\Domain\Contact\Actions\CreateContact;
use App\Domain\Contact\Actions\DeleteContact;
use App\Domain\Contact\Actions\UpdateContact;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactAddress;
use App\Domain\Document\Models\Document;
use App\Enums\Timezone;
use App\Http\Controllers\Concerns\HasImageSupport;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Mail\ContactPortalLink;
use App\Models\AccountSettings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    use AuthorizesRequests, HasImageSupport, HasSchemaSupport, ValidatesRequests;

    protected string $domainName = 'Contact';

    protected string $recordType = 'contacts';

    protected string $recordTitle = 'Contact';

    protected Contact $recordModel;

    public function __construct(
        protected CreateContact $createContact,
        protected UpdateContact $updateContact,
        protected DeleteContact $deleteContact,
    ) {
        $this->middleware('auth');
        $this->recordModel = new Contact;
    }

    protected function getUnwrappedFieldsSchema(): array
    {
        $raw = $this->getFieldsSchema();
        if (! is_array($raw)) {
            return [];
        }

        $unwrapped = isset($raw['fields']) ? $raw['fields'] : $raw;

        return is_array($unwrapped) ? $unwrapped : [];
    }

    /**
     * Mirrors RecordController detail/index eager-load shape for record selects, morphs, sublists, and specs.
     *
     * @return array<string, mixed>
     */
    protected function buildEagerLoadConstraints(array $fieldsSchema, ?array $formSchema): array
    {
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
                } elseif ($fieldDef['typeDomain'] === 'Vendor') {
                    $relationships[$relationshipName] = function ($query): void {
                        $query->select(['id', 'display_name', 'primary_contact_id'])
                            ->with(['contacts' => function ($q): void {
                                $q->select(['id', 'display_name', 'first_name', 'last_name', 'email', 'vendor_id']);
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

        if (is_array($formSchema) && isset($formSchema['sublists']) && is_array($formSchema['sublists'])) {
            foreach ($formSchema['sublists'] as $sublist) {
                if (isset($sublist['modelRelationship'])) {
                    $relationships[$sublist['modelRelationship']] = function ($query) {
                        $query->select('*');
                    };
                }
            }
        }

        $formGroups = is_array($formSchema) ? ($formSchema['form'] ?? $formSchema) : [];
        $hasSpecsGroup = is_array($formGroups) && collect($formGroups)
            ->contains(fn ($g) => is_array($g) && ($g['type'] ?? null) === 'specs');

        if ($hasSpecsGroup) {
            $relationships['specValues'] = fn ($q) => $q->with('definition');
        }

        return $relationships;
    }

    public function index(Request $request)
    {
        $columns = $this->getSchemaColumns();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        $dbColumns = Schema::connection($this->recordModel->getConnectionName())
            ->getColumnListing($this->recordModel->getTable());

        $actualColumns = [];
        foreach ($columns as $column) {
            if (strpos($column, '.') === false && in_array($column, $dbColumns, true)) {
                $actualColumns[] = $column;
            }
        }

        if (! in_array('id', $actualColumns, true)) {
            $actualColumns[] = 'id';
        }

        $relationships = $this->buildEagerLoadConstraints($fieldsSchema, null);

        $query = Contact::query()->select($actualColumns)->with($relationships);

        $searchQuery = $request->get('search');
        if ($searchQuery && trim($searchQuery) !== '') {
            $term = '%'.strtolower(trim($searchQuery)).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(contacts.display_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(contacts.first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(contacts.last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(contacts.email) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(contacts.phone) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(contacts.mobile) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(contacts.company) LIKE ?', [$term]);
            });
        }

        $filtersParam = $request->get('filters');
        if ($filtersParam) {
            try {
                $filters = json_decode(urldecode($filtersParam), true);
                if (is_array($filters)) {
                    $query = $this->applyFilters($query, $filters, $fieldsSchema);
                }
            } catch (\Exception) {
                // ignore invalid filters
            }
        }

        $roleFilter = $request->query('role');
        $roleFilter = is_string($roleFilter) && in_array($roleFilter, ['lead', 'customer', 'vendor'], true)
            ? $roleFilter
            : null;
        if ($roleFilter === 'lead') {
            $query->whereHas('leads');
        } elseif ($roleFilter === 'customer') {
            $query->whereHas('customer');
        } elseif ($roleFilter === 'vendor') {
            $query->whereHas('vendors');
        }

        $query->withExists('leads')
            ->withExists('customer')
            ->withExists('vendors');

        $query->orderByRaw('LOWER(contacts.display_name) ASC');

        $perPage = (int) $request->get('per_page', 15);
        $records = $query->paginate($perPage);

        $records->getCollection()->transform(function (Contact $contact) {
            $contact->setAttribute('contact_roles', [
                'lead' => (bool) $contact->leads_exists,
                'customer' => (bool) $contact->customer_exists,
                'vendor' => (bool) $contact->vendors_exists,
            ]);

            return $contact;
        });

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

        return inertia('Tenant/Contact/Index', [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'pluralTitle' => Str::plural($this->recordTitle),
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'roleFilter' => $roleFilter,
        ]);
    }

    public function create()
    {
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

        return inertia('Tenant/Contact/Create', [
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
                if (($fieldDef['type'] ?? '') === 'image' && $request->hasFile($fieldKey)) {
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

            $result = ($this->createContact)($data);

            if (! is_array($result)) {
                $result = ['success' => true, 'record' => $result];
            }

            if ($result['success']) {
                if ($request->ajax() && ! $request->header('X-Inertia')) {
                    $rels = $this->buildEagerLoadConstraints($fieldsSchema, $this->getFormSchema());
                    $record = Contact::query()->with($rels)->find($result['record']->id);

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

            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function show(Request $request, int $contact)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema() ?? [];
        $relationships = $this->buildEagerLoadConstraints($fieldsSchema, $formSchema);

        if (! isset($relationships['customer'])) {
            $relationships['customer'] = function ($query): void {
                $query->select('*');
            };
        }
        if (! isset($relationships['vendors'])) {
            $relationships['vendors'] = function ($query): void {
                $query->select(['vendors.id', 'vendors.display_name', 'vendors.primary_contact_id']);
            };
        }
        if (! isset($relationships['leads'])) {
            $relationships['leads'] = function ($query): void {
                $query->select(['id', 'contact_id']);
            };
        }

        $record = Contact::query()->with($relationships)->findOrFail($contact);

        $formGroups = $formSchema['form'] ?? $formSchema;
        $hasSpecsGroup = is_array($formGroups) && collect($formGroups)
            ->contains(fn ($g) => is_array($g) && ($g['type'] ?? null) === 'specs');

        $availableSpecs = $hasSpecsGroup && isset($record->type)
            ? AvailableAssetSpecsCache::get((int) $record->type)
            : [];

        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

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
                'availableSpecs' => $availableSpecs,
            ]);
        }

        return inertia('Tenant/Contact/Show', [
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
            'availableSpecs' => $availableSpecs,
        ]);
    }

    public function edit(int $contact)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema() ?? [];
        $relationships = $this->buildEagerLoadConstraints($fieldsSchema, $formSchema);

        if (! isset($relationships['customer'])) {
            $relationships['customer'] = function ($query): void {
                $query->select('*');
            };
        }
        if (! isset($relationships['vendors'])) {
            $relationships['vendors'] = function ($query): void {
                $query->select(['vendors.id', 'vendors.display_name', 'vendors.primary_contact_id']);
            };
        }
        if (! isset($relationships['leads'])) {
            $relationships['leads'] = function ($query): void {
                $query->select(['id', 'contact_id']);
            };
        }

        $record = Contact::query()->with($relationships)->findOrFail($contact);

        $formGroups = $formSchema['form'] ?? $formSchema;
        $hasSpecsGroup = is_array($formGroups) && collect($formGroups)
            ->contains(fn ($g) => is_array($g) && ($g['type'] ?? null) === 'specs');

        $availableSpecs = $hasSpecsGroup && isset($record->type)
            ? AvailableAssetSpecsCache::get((int) $record->type)
            : [];

        $enumOptions = $this->getEnumOptions();
        $account = AccountSettings::getCurrent();

        return inertia('Tenant/Contact/Edit', [
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

    protected function inertiaUpdateSuccessRedirect(Request $request, int|string $id): RedirectResponse
    {
        return back()->with('success', $this->domainName.' updated successfully');
    }

    public function update(Request $request, int $contact, PublicStorage $publicStorage)
    {
        try {
            $data = $request->all();
            $fieldsSchema = $this->getUnwrappedFieldsSchema();

            foreach ($fieldsSchema as $fieldKey => $fieldDef) {
                if (($fieldDef['type'] ?? '') !== 'image') {
                    continue;
                }

                $currentRecord = Contact::query()->find($contact);
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

            $result = ($this->updateContact)($contact, $data);

            if ($result['success']) {
                if ($request->ajax() && ! $request->header('X-Inertia')) {
                    $rels = $this->buildEagerLoadConstraints($fieldsSchema, $this->getFormSchema());
                    $record = Contact::query()->with($rels)->find($contact);

                    return response()->json([
                        'success' => true,
                        'record' => $record,
                        'message' => $this->domainName.' updated successfully',
                    ]);
                }

                return $this->inertiaUpdateSuccessRedirect($request, $contact);
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

    public function indexAddresses(int $contact): \Illuminate\Http\JsonResponse
    {
        $contactModel = Contact::query()->findOrFail($contact);

        $addresses = $contactModel->addresses()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get(['id', 'label', 'is_primary', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country', 'latitude', 'longitude']);

        return response()->json(['addresses' => $addresses]);
    }

    public function storeAddress(Request $request, int $contact): RedirectResponse
    {
        $contactModel = Contact::query()->findOrFail($contact);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $wantsPrimary = $request->boolean('is_primary');

        DB::transaction(function () use ($contactModel, $validated, $wantsPrimary): void {
            if ($wantsPrimary) {
                $contactModel->addresses()->update(['is_primary' => false]);
            }

            $shouldBePrimary = $wantsPrimary || ! $contactModel->addresses()->exists();

            ContactAddress::create([
                'contact_id' => $contactModel->id,
                'label' => $validated['label'] ?? null,
                'is_primary' => $shouldBePrimary,
                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city' => $validated['city'] ?: null,
                'state' => $validated['state'] ?: null,
                'postal_code' => $validated['postal_code'] ?: null,
                'country' => $validated['country'] ?: null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]);
        });

        return back()->with('success', 'Address added.');
    }

    public function sendPortalLink(int $contact): RedirectResponse
    {
        $record = Contact::query()->findOrFail($contact);

        $email = trim((string) ($record->email ?? ''));
        if ($email === '') {
            return back()->with('error', 'This contact does not have a primary email address.');
        }

        $hasCustomerProfile = $record->customer()->exists();

        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;
        $root = $domain ? 'https://'.$domain : rtrim((string) config('app.url'), '/');
        $loginUrl = $root.'/portal/login';
        $registerUrl = $root.'/portal/register';

        $settings = AccountSettings::getCurrent();
        Mail::to($email)->send(new ContactPortalLink($record, $settings, $loginUrl, $registerUrl, $hasCustomerProfile));

        return back()->with('success', 'Portal links sent to '.$email.'.');
    }

    public function destroy(int $contact)
    {
        $result = ($this->deleteContact)($contact);

        if ($result['success']) {
            return redirect()
                ->route($this->recordType.'.index')
                ->with('success', $this->domainName.' deleted successfully');
        }

        return back()
            ->with('error', $result['message'] ?? 'Failed to delete '.$this->recordTitle);
    }

    /**
     * Delete multiple contacts in one request (used by table bulk actions).
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:contacts,id',
        ]);

        $deleted = 0;
        $errors = [];

        DB::transaction(function () use ($validated, &$deleted, &$errors): void {
            foreach ($validated['ids'] as $id) {
                $result = ($this->deleteContact)((int) $id);
                if ($result['success']) {
                    $deleted++;
                } else {
                    $errors[] = $id.': '.($result['message'] ?? 'failed');
                }
            }
        });

        if ($deleted === 0) {
            return back()->with('error', 'No contacts were deleted.'.(count($errors) ? ' '.implode(' ', $errors) : ''));
        }

        $message = $deleted === 1
            ? '1 contact deleted.'
            : "{$deleted} contacts deleted.";

        if (count($errors)) {
            Log::warning('Contact bulk delete partial failures', ['errors' => $errors]);
            $message .= ' Some rows could not be removed.';
        }

        return redirect()
            ->route($this->recordType.'.index', $request->only(['role', 'search', 'filters', 'per_page', 'sort', 'direction']))
            ->with('success', $message);
    }
}
