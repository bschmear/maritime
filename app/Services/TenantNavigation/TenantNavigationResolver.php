<?php

declare(strict_types=1);

namespace App\Services\TenantNavigation;

use App\Domain\Integration\Models\Integration;
use App\Domain\NavigationMenu\Models\NavigationMenu;
use App\Domain\NavigationMenu\Models\NavigationMenuItem;
use App\Domain\Role\Models\Role;
use App\Enums\Integration\IntegrationType;
use App\Support\Tenant\TenantNavigationCache;
use App\Tenancy\CurrentTenantProfile;
use Illuminate\Support\Collection;

class TenantNavigationResolver
{
    public function __construct(
        private readonly CurrentTenantProfile $tenantProfile,
    ) {}

    /**
     * @return list<array{name: string, href?: string, children?: list<array<string, mixed>>}>
     */
    public function resolve(?string $roleSlug): array
    {
        return TenantNavigationCache::remember($roleSlug, function () use ($roleSlug) {
            $menu = $this->findMenuForRole($roleSlug);
            if ($menu === null) {
                return [];
            }

            $items = NavigationMenuItem::query()
                ->where('navigation_menu_id', $menu->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $treeWithPermissions = TenantNavigationTree::buildWithPermissions($items);

            $filteredByPermission = TenantNavigationTree::filterByPermission(
                $treeWithPermissions,
                fn (string $permission) => $this->tenantProfile->hasPermission($permission),
            );

            return TenantNavigationTree::filterByIntegration(
                $filteredByPermission,
                fn (string $slug) => $this->integrationIsActive($slug),
            );
        });
    }

    private function integrationIsActive(string $slug): bool
    {
        $type = collect(IntegrationType::cases())->first(fn (IntegrationType $case) => $case->slug() === $slug);

        if ($type === null) {
            return false;
        }

        return Integration::query()
            ->where('integration_type', $type)
            ->where('active', true)
            ->exists();
    }

    public function findMenuForRole(?string $roleSlug): ?NavigationMenu
    {
        if ($roleSlug !== null && $roleSlug !== '') {
            $role = Role::query()->where('slug', $roleSlug)->first();
            if ($role !== null) {
                $roleMenu = NavigationMenu::query()->where('role_id', $role->id)->first();
                if ($roleMenu !== null) {
                    return $roleMenu;
                }
            }
        }

        return NavigationMenu::query()->where('is_default', true)->first();
    }

    /**
     * @return list<array{id: int, label: string, route_name: string|null, permission_key: string|null, parent_id: int|null, sort_order: int, permission_granted_for_role: bool, children: list<array<string, mixed>>}>
     */
    public function editorTree(NavigationMenu $menu): array
    {
        $items = NavigationMenuItem::query()
            ->where('navigation_menu_id', $menu->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rolePermissionKeys = $this->rolePermissionKeys($menu);

        return $this->buildEditorBranch($items, null, $rolePermissionKeys);
    }

    /**
     * @return list<string>
     */
    private function rolePermissionKeys(NavigationMenu $menu): array
    {
        if ($menu->role_id === null) {
            return [];
        }

        $menu->loadMissing('role.permissions');
        if ($menu->role === null) {
            return [];
        }

        return $menu->role->permissionKeys();
    }

    /**
     * @param  Collection<int, NavigationMenuItem>  $items
     * @param  list<string>  $rolePermissionKeys
     * @return list<array<string, mixed>>
     */
    private function buildEditorBranch(Collection $items, ?int $parentId, array $rolePermissionKeys): array
    {
        $branch = [];

        foreach ($items->where('parent_id', $parentId) as $item) {
            $permissionKey = $item->permission_key;
            $permissionGranted = $permissionKey === null
                || $rolePermissionKeys === []
                || in_array($permissionKey, $rolePermissionKeys, true);

            $branch[] = [
                'id' => $item->id,
                'label' => $item->label,
                'route_name' => $item->route_name,
                'permission_key' => $permissionKey,
                'parent_id' => $item->parent_id,
                'sort_order' => $item->sort_order,
                'permission_granted_for_role' => $permissionGranted,
                'children' => $this->buildEditorBranch($items, $item->id, $rolePermissionKeys),
            ];
        }

        return $branch;
    }

    public function cloneMenuItems(NavigationMenu $source, NavigationMenu $target): void
    {
        $sourceItems = NavigationMenuItem::query()
            ->where('navigation_menu_id', $source->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $idMap = [];

        foreach ($sourceItems->whereNull('parent_id') as $item) {
            $this->cloneItemRecursive($sourceItems, $item, null, $target->id, $idMap);
        }
    }

    /**
     * @param  Collection<int, NavigationMenuItem>  $allItems
     * @param  array<int, int>  $idMap
     */
    private function cloneItemRecursive(
        Collection $allItems,
        NavigationMenuItem $item,
        ?int $newParentId,
        int $menuId,
        array &$idMap,
    ): void {
        $clone = NavigationMenuItem::query()->create([
            'navigation_menu_id' => $menuId,
            'parent_id' => $newParentId,
            'label' => $item->label,
            'route_name' => $item->route_name,
            'permission_key' => $item->permission_key,
            'sort_order' => $item->sort_order,
        ]);

        $idMap[$item->id] = $clone->id;

        foreach ($allItems->where('parent_id', $item->id) as $child) {
            $this->cloneItemRecursive($allItems, $child, $clone->id, $menuId, $idMap);
        }
    }
}
