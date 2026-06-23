<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Delivery\Models\Delivery;
use App\Domain\Delivery\Support\DeliveryApproverResolver;
use App\Domain\Location\Actions\CreateLocation;
use App\Domain\Location\Actions\DeleteLocation;
use App\Domain\Location\Actions\UpdateLocation;
use App\Domain\Location\Models\Location;
use App\Domain\Location\Models\LocationLayout;
use App\Domain\Location\Support\LocationUnitsPayload;
use App\Enums\Inventory\UnitStatus;
use App\Enums\Locations\LocationType;
use App\Enums\Timezone;
use App\Http\Controllers\Concerns\EnforcesTenantRecordPermissions;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use App\Models\AccountSettings;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;

class LocationController extends BaseController
{
    use AuthorizesRequests, EnforcesTenantRecordPermissions, HasSchemaSupport, ValidatesRequests;

    protected string $domainName = 'Location';

    protected string $recordType = 'locations';

    protected string $recordTitle = 'Location';

    protected Location $recordModel;

    public function __construct(
        protected CreateLocation $createLocation,
        protected UpdateLocation $updateLocation,
        protected DeleteLocation $deleteLocation,
    ) {
        $this->middleware('auth');
        $this->recordModel = new Location;
        $this->registerTenantRecordPermissionMiddleware();
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

    protected function locationEnumOptions(): array
    {
        return array_merge($this->getEnumOptions(), [
            LocationType::class => LocationType::options(),
            Timezone::class => Timezone::options(),
        ]);
    }

    public function index(Request $request): InertiaResponse
    {
        $schema = $this->getTableSchema();
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $enumOptions = $this->locationEnumOptions();

        $relationships = $this->indexRelationships($fieldsSchema);
        $columns = $this->getSchemaColumns();
        if (! in_array('id', $columns, true)) {
            $columns[] = 'id';
        }

        $query = Location::query()->select($columns)->with($relationships);

        $searchQuery = $request->get('search');
        if (is_string($searchQuery) && trim($searchQuery) !== '') {
            $query->whereRaw('LOWER(display_name) LIKE ?', ['%'.strtolower(trim($searchQuery)).'%']);
        }

        $appliedFilters = $this->resolveIndexFiltersFromRequest($request, $schema);
        if ($appliedFilters !== []) {
            $query = $this->applyFilters($query, $appliedFilters, $fieldsSchema);
        }

        $tableName = (new Location)->getTable();
        $dbColumns = \Schema::connection((new Location)->getConnectionName())->getColumnListing($tableName);
        if (! $this->applyRecordIndexSort($query, $request, $schema, $dbColumns, $tableName, $columns, $fieldsSchema)) {
            $query->orderByRaw('LOWER(display_name) ASC');
        }

        $records = $query->paginate(table_per_page($request));

        return inertia('Tenant/Location/Index', [
            'records' => $records,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'pluralTitle' => Str::plural($this->recordTitle),
            'schema' => $schema,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'appliedFilters' => $appliedFilters,
        ]);
    }

    public function show(Request $request, Location $location): InertiaResponse
    {
        $fieldsSchema = $this->getUnwrappedFieldsSchema();
        $formSchema = $this->getFormSchema();
        $relationships = $this->showRelationships($fieldsSchema, $formSchema);

        $record = Location::query()->with($relationships)->findOrFail($location->id);
        $record->loadMissing(['managerUser', 'deliveryApprover']);

        $effectiveApprover = DeliveryApproverResolver::forLocation($record);
        $pendingCount = Delivery::query()
            ->where('pending_request', true)
            ->where('location_id', $record->id)
            ->count();

        $locationTypeMatch = collect(LocationType::options())->firstWhere('id', (int) $record->location_type);
        $locationTypeLabel = $locationTypeMatch['name'] ?? null;

        $layouts = $record->layouts()->orderBy('id')->get(['id', 'name', 'width_ft', 'height_ft']);

        if ($layouts->isEmpty()) {
            LocationLayout::query()->create([
                'location_id' => $record->id,
                'name' => 'Default',
                'width_ft' => 60,
                'height_ft' => 40,
                'grid_size' => 1,
                'scale' => 10,
                'meta' => [],
            ]);
            $layouts = $record->layouts()->orderBy('id')->get(['id', 'name', 'width_ft', 'height_ft']);
        }

        $requestedLayoutId = $request->integer('layout');
        $activeLayout = $requestedLayoutId > 0
            ? $layouts->firstWhere('id', $requestedLayoutId)
            : null;
        $activeLayoutModel = $activeLayout !== null
            ? LocationLayout::query()->find($activeLayout['id'])
            : LocationLayout::query()->find($layouts->first()['id']);

        $layoutSpace = LocationUnitsPayload::layoutSpaceFrom($activeLayoutModel);
        $layoutUnits = $activeLayoutModel !== null
            ? LocationUnitsPayload::forLayoutSidebar($activeLayoutModel, $record)
            : [];

        return inertia('Tenant/Location/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'domainName' => $this->domainName,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $this->locationEnumOptions(),
            'locationTypeLabel' => $locationTypeLabel,
            'effectiveDeliveryApprover' => $effectiveApprover ? [
                'id' => $effectiveApprover->id,
                'display_name' => $effectiveApprover->display_name,
                'uses_manager_fallback' => $record->delivery_approver_user_id === null
                    && $record->manager_user_id !== null
                    && (int) $record->manager_user_id === (int) $effectiveApprover->id,
            ] : null,
            'pendingDeliveryRequestCount' => $pendingCount,
            'canManageDeliveryApprovers' => tenant_has_permission('location.edit'),
            'layouts' => $layouts->values()->all(),
            'activeLayoutId' => $activeLayoutModel?->id,
            'layoutSpace' => $layoutSpace,
            'layoutUnits' => $layoutUnits,
            'unitStatusOptions' => UnitStatus::options(),
            'defaultUnitStatusFilter' => LocationUnitsPayload::defaultStatusFilterIds(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return inertia('Tenant/Location/Create', [
            'recordType' => $this->recordType,
            'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
            'enumOptions' => $this->locationEnumOptions(),
            'timezones' => Timezone::options(),
            'account' => AccountSettings::getCurrent(),
        ]);
    }

    public function edit(Location $location): InertiaResponse
    {
        $record = Location::with(['managerUser', 'deliveryApprover'])->findOrFail($location->id);

        return inertia('Tenant/Location/Edit', [
            'record' => $record,
            'recordType' => $this->recordType,
            'fieldsSchema' => $this->getUnwrappedFieldsSchema(),
            'enumOptions' => $this->locationEnumOptions(),
            'timezones' => Timezone::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $data = $request->all();
            $fieldsSchema = $this->getUnwrappedFieldsSchema();

            $schemaFailure = $this->validateSchemaFormInput($data, $this->getFormSchema(), $fieldsSchema);
            if ($schemaFailure !== null) {
                return back()->withInput()->withErrors($schemaFailure['errors']);
            }

            $result = ($this->createLocation)($data);
            if (! ($result['success'] ?? false)) {
                return back()->withInput()->withErrors(['form' => $result['message'] ?? 'Could not create location.']);
            }

            return redirect()
                ->route('locations.show', $result['record']->id)
                ->with('success', 'Location created successfully.')
                ->with('recordId', $result['record']->id);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        try {
            $data = $request->all();
            $fieldsSchema = $this->getUnwrappedFieldsSchema();

            $schemaFailure = $this->validateSchemaFormInput($data, $this->getFormSchema(), $fieldsSchema);
            if ($schemaFailure !== null) {
                return back()->withInput()->withErrors($schemaFailure['errors']);
            }

            $result = ($this->updateLocation)($location->id, $data);
            if (! ($result['success'] ?? false)) {
                return back()->withInput()->withErrors(['form' => $result['message'] ?? 'Could not update location.']);
            }

            return redirect()
                ->route('locations.show', $location->id)
                ->with('success', 'Location updated successfully.');
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
    }

    public function destroy(Location $location): RedirectResponse
    {
        $result = ($this->deleteLocation)($location->id);

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Could not delete location.');
        }

        return redirect()
            ->route('locations.index')
            ->with('success', 'Location deleted successfully.');
    }

    public function attachRelationship(Request $request, Location $location)
    {
        $request->validate([
            'relationship' => 'required|string',
            'related_id' => 'required|integer',
        ]);

        try {
            $relationship = $request->input('relationship');
            $relatedId = (int) $request->input('related_id');

            if (! method_exists($location, $relationship)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' does not exist on this model.",
                ], 400);
            }

            $relationshipInstance = $location->{$relationship}();
            if (! ($relationshipInstance instanceof BelongsToMany)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' is not a Many-to-Many relationship.",
                ], 400);
            }

            if ($relationshipInstance->where($relationshipInstance->getRelated()->getQualifiedKeyName(), $relatedId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This record is already attached.',
                ], 400);
            }

            $relationshipInstance->attach($relatedId);

            return response()->json([
                'success' => true,
                'message' => 'Record attached successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to attach record: '.$e->getMessage(),
            ], 500);
        }
    }

    public function detachRelationship(Request $request, Location $location)
    {
        $request->validate([
            'relationship' => 'required|string',
            'related_id' => 'required|integer',
        ]);

        try {
            $relationship = $request->input('relationship');
            $relatedId = (int) $request->input('related_id');

            if (! method_exists($location, $relationship)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' does not exist on this model.",
                ], 400);
            }

            $relationshipInstance = $location->{$relationship}();
            if (! ($relationshipInstance instanceof BelongsToMany)) {
                return response()->json([
                    'success' => false,
                    'message' => "Relationship '{$relationship}' is not a Many-to-Many relationship.",
                ], 400);
            }

            $relationshipInstance->detach($relatedId);

            return response()->json([
                'success' => true,
                'message' => 'Record detached successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to detach record: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $fieldsSchema
     * @return array<string, mixed>
     */
    protected function indexRelationships(array $fieldsSchema): array
    {
        $relationships = $this->getRelationshipsToLoad($fieldsSchema);

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (($fieldDef['type'] ?? null) !== 'record' || ! isset($fieldDef['typeDomain'])) {
                continue;
            }

            $relationshipName = $fieldDef['relationship'] ?? str_replace('_id', '', $fieldKey);
            if (! isset($relationships[$relationshipName])) {
                $relationships[$relationshipName] = fn ($query) => $query->select(['id', 'display_name']);
            }
        }

        return $relationships;
    }

    /**
     * @param  array<string, mixed>  $fieldsSchema
     * @param  array<string, mixed>|null  $formSchema
     * @return array<string, mixed>
     */
    protected function showRelationships(array $fieldsSchema, ?array $formSchema): array
    {
        $relationships = $this->indexRelationships($fieldsSchema);

        foreach ($formSchema['sublists'] ?? [] as $sublist) {
            if (isset($sublist['modelRelationship'])) {
                $name = $sublist['modelRelationship'];
                if ($name === 'systemLogs') {
                    $relationships[$name] = fn ($query) => $query
                        ->with(['user' => fn ($userQuery) => $userQuery->select(['id', 'display_name'])])
                        ->orderByDesc('created_at');
                } else {
                    $relationships[$name] = fn ($query) => $query->select('*');
                }
            }
        }

        return $relationships;
    }
}
