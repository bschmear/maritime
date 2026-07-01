<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\PublicStorage;
use App\Domain\Permission\Models\Permission;
use App\Domain\Role\Actions\CreateRole as CreateAction;
use App\Domain\Role\Actions\DeleteRole as DeleteAction;
use App\Domain\Role\Actions\UpdateRole as UpdateAction;
use App\Domain\Role\Models\Role as RecordModel;
use App\Enums\RecordType;
use App\Services\TenantStaffResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RoleController extends RecordController
{
    protected $recordType = 'Role';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'roles',
            'Role',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            'Role'
        );
    }

    /**
     * True when the signed-in central user maps to a tenant staff row with the Administrator role.
     */
    protected function tenantStaffIsAdministrator(): bool
    {
        $central = auth()->user();
        $staff = TenantStaffResolver::tenantStaffForWebUser($central);
        if (! $staff) {
            return false;
        }
        $staff->loadMissing('role');

        return $staff->role && $staff->role->slug === 'admin';
    }

    protected function indexInertiaProps(Request $request, $records, $schema, array $fieldsSchema, $formSchema, array $enumOptions, array $appliedFilters = [], bool $deferEnumOptions = false): array
    {
        $props = parent::indexInertiaProps($request, $records, $schema, $fieldsSchema, $formSchema, $enumOptions, $appliedFilters, $deferEnumOptions);

        if (! $this->tenantStaffIsAdministrator()) {
            $props['schema'] = array_merge($props['schema'] ?? [], ['hide_create_button' => true]);
        }

        return $props;
    }

    public function create()
    {
        abort_unless($this->tenantStaffIsAdministrator(), 403, 'Only administrators can create roles.');

        return parent::create();
    }

    public function store(Request $request, PublicStorage $publicStorage)
    {
        abort_unless($this->tenantStaffIsAdministrator(), 403, 'Only administrators can create roles.');

        return parent::store($request, $publicStorage);
    }

    public function update(Request $request, $id, PublicStorage $publicStorage)
    {
        abort_unless($this->tenantStaffIsAdministrator(), 403, 'Only administrators can update roles.');

        return parent::update($request, $id, $publicStorage);
    }

    public function destroy($id)
    {
        abort_unless($this->tenantStaffIsAdministrator(), 403, 'Only administrators can delete roles.');

        return parent::destroy($id);
    }

    /**
     * Show a specific role with users relationship loaded.
     */
    public function show(Request $request, $id)
    {
        $record = $this->recordModel->with([
            'users' => fn ($query) => $query
                ->orderByRaw('LOWER(COALESCE(display_name, \'\'))')
                ->orderByRaw('LOWER(last_name)')
                ->orderByRaw('LOWER(first_name)'),
            'permissions',
        ])->findOrFail($id);
        $formSchema = $this->getFormSchema();
        $fieldsSchema = $this->getFieldsSchema();
        $enumOptions = $this->getEnumOptions();

        $permissionsByDomain = $this->buildPermissionsByDomain();
        $assignedPermissionIds = $record->permissions->pluck('id')->values()->all();

        if (($request->wantsJson() || $request->ajax()) && ! $request->header('X-Inertia')) {
            return response()->json([
                'record' => $record,
                'recordType' => $this->recordType,
                'formSchema' => $formSchema,
                'fieldsSchema' => $fieldsSchema,
                'enumOptions' => $enumOptions,
                'permissionsByDomain' => $permissionsByDomain,
                'assignedPermissionIds' => $assignedPermissionIds,
            ]);
        }

        return inertia('Tenant/'.$this->domainName.'/Show', [
            'record' => $record,
            'recordType' => $this->recordType,
            'recordTitle' => $this->recordTitle,
            'formSchema' => $formSchema,
            'fieldsSchema' => $fieldsSchema,
            'enumOptions' => $enumOptions,
            'permissionsByDomain' => $permissionsByDomain,
            'assignedPermissionIds' => $assignedPermissionIds,
            'canManageRoles' => $this->tenantStaffIsAdministrator(),
        ]);
    }

    /**
     * @return list<array{domain: string, domainLabel: string, permissions: list<array{id: int, key: string, action: string, label: string}>}>
     */
    protected function buildPermissionsByDomain(): array
    {
        $actionRank = ['view' => 1, 'create' => 2, 'edit' => 3, 'delete' => 4];

        $permissions = Permission::query()
            ->orderBy('domain')
            ->get();

        $grouped = $permissions->groupBy('domain')->map(
            fn (Collection $rows) => $rows->sortBy(fn (Permission $p) => $actionRank[$p->action] ?? 99)->values()
        );

        $out = [];
        foreach ($grouped->keys() as $domain) {
            $type = RecordType::tryFrom($domain);
            $rows = $grouped->get($domain, collect());
            $out[] = [
                'domain' => $domain,
                'domainLabel' => $type?->title() ?? ucfirst($domain),
                'permissions' => $rows->map(fn (Permission $p) => [
                    'id' => $p->id,
                    'key' => $p->key,
                    'action' => $p->action,
                    'label' => $p->label,
                ])->values()->all(),
            ];
        }

        usort($out, fn (array $a, array $b) => strcasecmp($a['domainLabel'], $b['domainLabel']));

        return $out;
    }
}
