<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\AssetUnit\Models\AssetUnit;
use App\Domain\ConsignmentAgreement\Actions\CreateConsignmentAgreement;
use App\Domain\ConsignmentAgreement\Actions\DeleteConsignmentAgreement;
use App\Domain\ConsignmentAgreement\Actions\UpdateConsignmentAgreement;
use App\Domain\ConsignmentAgreement\Models\ConsignmentAgreement;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactAddress;
use App\Enums\Timezone;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Models\AccountSettings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ConsignmentAgreementController extends BaseController
{
    use AuthorizesRequests, HasSchemaSupport, ValidatesRequests;

    protected string $domainName = 'ConsignmentAgreement';

    protected ConsignmentAgreement $recordModel;

    public function __construct(
        protected CreateConsignmentAgreement $createConsignmentAgreement,
        protected UpdateConsignmentAgreement $updateConsignmentAgreement,
        protected DeleteConsignmentAgreement $deleteConsignmentAgreement,
    ) {
        $this->middleware('auth');
        $this->recordModel = new ConsignmentAgreement;
    }

    /**
     * @return array<string, mixed>
     */
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
     * @return array<string, mixed>
     */
    protected function getEnumOptions(): array
    {
        $opts = HasSchemaSupport::enumOptionsFromUnwrappedFields($this->getUnwrappedFieldsSchema());

        try {
            $opts['asset_unit_id'] = AssetUnit::query()
                ->where('is_consignment', true)
                ->with(['asset:id,display_name'])
                ->select(['id', 'asset_id', 'serial_number', 'hin', 'sku'])
                ->orderByDesc('id')
                ->limit(500)
                ->get()
                ->map(fn (AssetUnit $u) => [
                    'id' => $u->id,
                    'name' => $u->display_name,
                    'value' => $u->id,
                ])
                ->all();
        } catch (\Throwable $e) {
            \Log::warning('ConsignmentAgreement getEnumOptions asset_unit_id: '.$e->getMessage());
            $opts['asset_unit_id'] = [];
        }

        try {
            $opts['owner_contact_id'] = Contact::query()
                ->select(['id', 'display_name'])
                ->orderByDesc('id')
                ->limit(500)
                ->get()
                ->map(fn (Contact $c) => [
                    'id' => $c->id,
                    'name' => $c->display_name,
                    'value' => $c->id,
                ])
                ->all();
        } catch (\Throwable $e) {
            \Log::warning('ConsignmentAgreement getEnumOptions owner_contact_id: '.$e->getMessage());
            $opts['owner_contact_id'] = [];
        }

        return $opts;
    }

    /**
     * @return array<string, mixed>
     */
    private function createEditSharedProps(): array
    {
        return [
            'recordType' => 'consignmentagreements',
            'recordTitle' => 'Consignment agreement',
            'domainName' => $this->domainName,
            'formSchema' => $this->getFormSchema(),
            'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
            'enumOptions' => $this->getEnumOptions(),
            'account' => AccountSettings::getCurrent(),
            'timezones' => Timezone::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRelationships(): array
    {
        return [
            'assetUnit' => fn ($q) => $q->select(['id', 'asset_id', 'serial_number', 'hin', 'sku', 'is_consignment', 'asking_price', 'customer_id'])
                ->with([
                    'asset' => fn ($aq) => $aq->select(['id', 'display_name']),
                    'customer' => fn ($cq) => $cq->select(['id', 'contact_id']),
                ]),
            'ownerContact' => fn ($q) => $q->select(['id', 'display_name', 'phone', 'mobile', 'email']),
            'ownerContactAddress' => fn ($q) => $q->select([
                'id', 'contact_id', 'label', 'is_primary',
                'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country',
            ]),
        ];
    }

    public function index(Request $request)
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $schema = $this->getTableSchema() ?? ['columns' => []];
        $formSchema = $this->getFormSchema();
        $enumOptions = $this->getEnumOptions();

        $tableName = $this->recordModel->getTable();
        $dbColumns = Schema::connection($this->recordModel->getConnectionName())->getColumnListing($tableName);

        $actualColumns = ['id', 'uuid', 'sequence', 'asset_unit_id', 'agreement_date', 'signed_at', 'created_at', 'updated_at'];
        $actualColumns = array_values(array_filter($actualColumns, fn ($c) => in_array($c, $dbColumns, true)));

        $query = ConsignmentAgreement::query()
            ->select(array_map(static fn (string $c) => $tableName.'.'.$c, $actualColumns))
            ->with(['assetUnit' => fn ($q) => $q->select(['id', 'asset_id', 'serial_number', 'hin', 'sku'])->with(['asset:id,display_name'])]);

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($like, $search, $tableName) {
                $q->whereRaw('LOWER('.$tableName.'.uuid) LIKE ?', [$like])
                    ->orWhereHas('ownerContact', function ($cq) use ($like) {
                        $cq->whereRaw('LOWER(COALESCE(contacts.display_name, \'\')) LIKE ?', [$like]);
                    });
                if (ctype_digit($search)) {
                    $n = (int) $search;
                    $q->orWhere($tableName.'.id', $n);
                    if (in_array('sequence', $dbColumns, true)) {
                        $q->orWhere($tableName.'.sequence', $n);
                    }
                }
            });
        }

        $sort = (string) $request->get('sort', 'created_at');
        $dir = strtolower((string) $request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['id', 'agreement_date', 'signed_at', 'created_at', 'updated_at'];
        if (in_array('sequence', $dbColumns, true)) {
            $sortable[] = 'sequence';
        }
        if (in_array($sort, $sortable, true)) {
            $query->orderBy($tableName.'.'.$sort, $dir);
        } else {
            $query->orderBy($tableName.'.created_at', 'desc');
        }

        $perPage = (int) $request->get('per_page', 15);
        $records = $query->paginate($perPage > 0 ? $perPage : 15)->withQueryString();

        return inertia('Tenant/ConsignmentAgreement/Index', [
            'records' => $records,
            'recordType' => 'consignmentagreements',
            'recordTitle' => 'Consignment agreement',
            'pluralTitle' => 'Consignment agreements',
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
        ]);
    }

    public function create(Request $request)
    {
        $prefill = [];

        $auId = (int) $request->query('asset_unit_id', 0);
        if ($auId > 0) {
            $unit = AssetUnit::query()
                ->whereKey($auId)
                ->where('is_consignment', true)
                ->with(['asset:id,display_name', 'assetVariant:id,display_name', 'customer.contact'])
                ->first();
            if ($unit) {
                $contactId = $unit->customer?->contact_id;
                $addrModel = null;
                if ($contactId) {
                    $addrModel = ContactAddress::query()
                        ->where('contact_id', $contactId)
                        ->orderByDesc('is_primary')
                        ->orderBy('id')
                        ->first();
                }
                $addrId = $addrModel?->id;
                $prefill = [
                    'asset_unit_id' => $unit->id,
                    'agreement_date' => now()->toDateString(),
                    'boat_description' => $unit->asset?->display_name,
                    'motor_description' => $unit->assetVariant?->display_name,
                    'asking_boat' => $unit->asking_price,
                    'owner_contact_id' => $contactId,
                    'owner_contact_address_id' => $addrId,
                    'lock_owner_contact' => $contactId !== null,
                    'owner_contact' => $unit->customer?->contact,
                    'owner_contact_address' => $addrModel,
                ];
            }
        }

        return inertia('Tenant/ConsignmentAgreement/Create', array_merge($this->createEditSharedProps(), [
            'prefill' => $prefill,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'asset_unit_id' => 'required|integer|exists:asset_units,id',
            ]);

            $unit = AssetUnit::query()->findOrFail((int) $request->input('asset_unit_id'));
            abort_unless($unit->is_consignment, 422, 'This unit is not marked as consignment.');

            $existing = ConsignmentAgreement::query()
                ->where('asset_unit_id', $unit->id)
                ->unsigned()
                ->exists();
            if ($existing) {
                throw ValidationException::withMessages([
                    'asset_unit_id' => ['An unsigned agreement already exists for this unit.'],
                ]);
            }

            $data = $request->all();
            $data['asset_unit_id'] = $unit->id;

            $result = ($this->createConsignmentAgreement)($data);

            if (($result['success'] ?? false) && isset($result['record'])) {
                return redirect()
                    ->route('consignmentagreements.show', $result['record']->id)
                    ->with('success', 'Consignment agreement created.')
                    ->with('recordId', $result['record']->id);
            }

            return back()->withInput()->with('error', $result['message'] ?? 'Failed to create agreement.');
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function show(ConsignmentAgreement $consignmentagreement)
    {
        $record = ConsignmentAgreement::query()
            ->whereKey($consignmentagreement->getKey())
            ->with($this->detailRelationships())
            ->firstOrFail();

        $record->append('signature_url');

        return inertia('Tenant/ConsignmentAgreement/Show', array_merge($this->createEditSharedProps(), [
            'record' => $record,
            'canMutate' => $record->signed_at === null,
            'reviewUrl' => $record->uuid
                ? route('consignment-agreements.review', ['uuid' => $record->uuid])
                : null,
        ]));
    }

    public function edit(ConsignmentAgreement $consignmentagreement)
    {
        $record = ConsignmentAgreement::query()
            ->whereKey($consignmentagreement->getKey())
            ->with($this->detailRelationships())
            ->firstOrFail();

        abort_if($record->signed_at !== null, 403);

        return inertia('Tenant/ConsignmentAgreement/Edit', array_merge($this->createEditSharedProps(), [
            'record' => $record,
            'reviewUrl' => $record->uuid ? route('consignment-agreements.review', ['uuid' => $record->uuid]) : null,
        ]));
    }

    public function update(Request $request, ConsignmentAgreement $consignmentagreement): RedirectResponse
    {
        try {
            $record = ConsignmentAgreement::query()->whereKey($consignmentagreement->getKey())->firstOrFail();
            abort_if($record->signed_at !== null, 403);

            $result = ($this->updateConsignmentAgreement)($record->id, $request->all());

            if (($result['success'] ?? false) && isset($result['record'])) {
                return redirect()
                    ->route('consignmentagreements.show', $record->id)
                    ->with('success', 'Consignment agreement updated.')
                    ->with('recordId', $record->id);
            }

            return back()->withInput()->with('error', $result['message'] ?? 'Update failed.');
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function destroy(ConsignmentAgreement $consignmentagreement): RedirectResponse
    {
        $result = ($this->deleteConsignmentAgreement)((int) $consignmentagreement->getKey());

        if ($result['success'] ?? false) {
            return redirect()->route('consignmentagreements.index')->with('success', $result['message'] ?? 'Deleted.');
        }

        return back()->with('error', $result['message'] ?? 'Delete failed.');
    }

    public function storeNested(AssetUnit $assetunit): RedirectResponse
    {
        abort_unless($assetunit->is_consignment, 422, 'This unit is not marked as consignment.');

        $existing = $assetunit->consignmentAgreements()->unsigned()->latest('id')->first();
        if ($existing) {
            return redirect()
                ->route('consignmentagreements.edit', $existing->id)
                ->with('info', 'A draft consignment agreement already exists for this unit.');
        }

        $assetunit->load(['asset:id,display_name', 'assetVariant:id,display_name', 'customer']);

        abort_unless(
            $assetunit->customer?->contact_id,
            422,
            'The consignment unit’s customer must have a linked contact before creating an agreement.',
        );

        $contactId = $assetunit->customer?->contact_id;
        $addrId = null;
        if ($contactId) {
            $addrId = ContactAddress::query()
                ->where('contact_id', $contactId)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->value('id');
        }

        $agreement = ConsignmentAgreement::create([
            'asset_unit_id' => $assetunit->id,
            'agreement_date' => now()->toDateString(),
            'boat_description' => $assetunit->asset?->display_name,
            'motor_description' => $assetunit->assetVariant?->display_name,
            'asking_boat' => $assetunit->asking_price,
            'owner_contact_id' => $contactId,
            'owner_contact_address_id' => $addrId,
        ]);

        return redirect()
            ->route('consignmentagreements.edit', $agreement->id)
            ->with('success', 'Consignment agreement draft created. Fill in details and share the customer link.');
    }

    public function updateNested(Request $request, AssetUnit $assetunit): RedirectResponse
    {
        try {
            abort_unless($assetunit->is_consignment, 422, 'This unit is not marked as consignment.');

            $agreement = $assetunit->consignmentAgreements()->unsigned()->latest('id')->firstOrFail();

            $payload = $request->all();
            $payload['boat_title_signed_delivered'] = $request->boolean('boat_title_signed_delivered');

            foreach ([
                'asking_boat', 'asking_motor', 'asking_other', 'asking_sold',
                'minimum_boat', 'minimum_motor', 'minimum_other', 'minimum_sold',
            ] as $moneyKey) {
                if (array_key_exists($moneyKey, $payload) && $payload[$moneyKey] === '') {
                    $payload[$moneyKey] = null;
                }
            }

            $result = ($this->updateConsignmentAgreement)($agreement->id, $payload);

            if (($result['success'] ?? false) && isset($result['record'])) {
                return redirect()
                    ->route('consignmentagreements.edit', $agreement->id)
                    ->with('success', 'Consignment agreement updated.');
            }

            return back()->withInput()->with('error', $result['message'] ?? 'Update failed.');
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
    }
}
