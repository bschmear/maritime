<?php

declare(strict_types=1);

namespace App\Services\TenantNavigation;

use App\Domain\NavigationMenu\Models\NavigationMenu;
use App\Domain\NavigationMenu\Models\NavigationMenuItem;
use App\Support\Tenant\TenantNavigationCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;

class NavigationMenuSyncService
{
    /**
     * Replace all items for a menu from a nested editor payload.
     *
     * @param  list<array{id?: int|null, label: string, route_name?: string|null, children?: list<array<string, mixed>>}>  $nodes
     */
    public function sync(NavigationMenu $menu, array $nodes): void
    {
        DB::connection('tenant')->transaction(function () use ($menu, $nodes) {
            NavigationMenuItem::query()
                ->where('navigation_menu_id', $menu->id)
                ->delete();

            $sortOrder = 0;
            foreach ($nodes as $node) {
                $this->createNode($menu->id, $node, null, $sortOrder);
            }
        });

        TenantNavigationCache::bumpVersion();
    }

    public function syncFromDefaultFile(NavigationMenu $menu): void
    {
        $nodes = array_map(
            fn (array $node) => $this->configNodeToEditorNode($node),
            TenantDefaultNavigation::nodes(),
        );

        $this->sync($menu, $nodes);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{label: string, route_name: string|null, children: list<array<string, mixed>>}
     */
    private function configNodeToEditorNode(array $node): array
    {
        $route = isset($node['route']) ? (string) $node['route'] : null;

        return [
            'label' => (string) ($node['label'] ?? 'Untitled'),
            'route_name' => $route,
            'children' => array_map(
                fn (array $child) => $this->configNodeToEditorNode($child),
                is_array($node['children'] ?? null) ? $node['children'] : [],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function createNode(int $menuId, array $node, ?int $parentId, int &$sortOrder): void
    {
        $routeName = isset($node['route_name']) && $node['route_name'] !== ''
            ? (string) $node['route_name']
            : null;

        if ($routeName !== null && ! RouteFacade::has($routeName)) {
            $routeName = null;
        }

        $permissionKey = $routeName !== null
            ? TenantNavigationCatalog::permissionKeyForRoute($routeName)
            : null;

        $item = NavigationMenuItem::query()->create([
            'navigation_menu_id' => $menuId,
            'parent_id' => $parentId,
            'label' => (string) ($node['label'] ?? 'Untitled'),
            'route_name' => $routeName,
            'permission_key' => $permissionKey,
            'requires_integration' => $routeName !== null
                ? TenantNavigationCatalog::requiresIntegrationForRoute($routeName)
                : null,
            'sort_order' => $sortOrder++,
        ]);

        foreach ($node['children'] ?? [] as $child) {
            $this->createNode($menuId, $child, $item->id, $sortOrder);
        }
    }
}
