<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Permission\Models\Permission;
use App\Domain\Role\Models\Role;
use App\Enums\RecordType;
use App\Support\Tenant\TenantPermissionsCache;

class PermissionGenerator
{
    /**
     * Permission keys managers must not have (cannot add/remove tenant users).
     */
    private const MANAGER_EXCLUDED_PERMISSION_KEYS = [
        'user.create',
        'user.delete',
    ];

    /**
     * @return array{created: int, existing: int}
     */
    public function sync(): array
    {
        $actions = ['view', 'create', 'edit', 'delete'];
        $created = 0;
        $existing = 0;

        foreach (RecordType::cases() as $recordType) {
            foreach ($actions as $action) {
                $key = $recordType->key().'.'.$action;
                $permission = Permission::query()->firstOrCreate(
                    ['key' => $key],
                    [
                        'domain' => $recordType->key(),
                        'action' => $action,
                        'label' => $recordType->title().' '.ucfirst($action),
                    ]
                );

                if ($permission->wasRecentlyCreated) {
                    $created++;
                } else {
                    $existing++;
                }
            }
        }

        return ['created' => $created, 'existing' => $existing];
    }

    /**
     * Grant every permission to the admin role.
     */
    public function assignAllPermissionsToAdminRole(): void
    {
        $admin = Role::query()->where('slug', 'admin')->first();
        if (! $admin) {
            return;
        }

        $ids = Permission::query()->pluck('id')->all();
        $admin->permissions()->sync($ids);
        TenantPermissionsCache::bumpVersion();
    }

    /**
     * Apply default permission sets for seeded roles:
     * admin — all permissions
     * manager — all except creating/deleting users
     * employee — view + edit only (all domains)
     * guest — view only (all domains)
     */
    public function assignDefaultRolePermissions(): void
    {
        $this->assignAllPermissionsToAdminRole();

        $manager = Role::query()->where('slug', 'manager')->first();
        if ($manager) {
            $ids = Permission::query()
                ->whereNotIn('key', self::MANAGER_EXCLUDED_PERMISSION_KEYS)
                ->pluck('id')
                ->all();
            $manager->permissions()->sync($ids);
        }

        $employee = Role::query()->where('slug', 'employee')->first();
        if ($employee) {
            $ids = Permission::query()
                ->whereIn('action', ['view', 'edit'])
                ->pluck('id')
                ->all();
            $employee->permissions()->sync($ids);
        }

        $guest = Role::query()->where('slug', 'guest')->first();
        if ($guest) {
            $ids = Permission::query()
                ->where('action', 'view')
                ->pluck('id')
                ->all();
            $guest->permissions()->sync($ids);
        }

        TenantPermissionsCache::bumpVersion();
    }
}
