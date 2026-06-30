<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\NavigationMenu\Models\NavigationMenu;
use App\Domain\NavigationMenu\Models\NavigationMenuItem;
use App\Services\TenantNavigation\TenantNavigationCatalog;
use App\Support\Tenant\TenantNavigationCache;
use Illuminate\Database\Seeder;

class NavigationMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (NavigationMenu::query()->where('is_default', true)->exists()) {
            return;
        }

        $menu = NavigationMenu::query()->create([
            'name' => 'Default',
            'role_id' => null,
            'is_default' => true,
        ]);

        $nodes = config('tenant_navigation', []);
        $sortOrder = 0;

        foreach ($nodes as $node) {
            $this->createNode($menu->id, $node, null, $sortOrder);
        }

        TenantNavigationCache::bumpVersion();
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function createNode(int $menuId, array $node, ?int $parentId, int &$sortOrder): void
    {
        $route = isset($node['route']) ? (string) $node['route'] : null;
        $permissionKey = $route !== null
            ? TenantNavigationCatalog::permissionKeyForRoute($route)
            : null;

        $item = NavigationMenuItem::query()->create([
            'navigation_menu_id' => $menuId,
            'parent_id' => $parentId,
            'label' => (string) $node['label'],
            'route_name' => $route,
            'permission_key' => $permissionKey,
            'sort_order' => $sortOrder++,
        ]);

        foreach ($node['children'] ?? [] as $child) {
            $this->createNode($menuId, $child, $item->id, $sortOrder);
        }
    }
}
