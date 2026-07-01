<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\NavigationMenu\Models\NavigationMenu;
use App\Domain\NavigationMenu\Models\NavigationMenuItem;
use App\Services\TenantNavigation\TenantNavigationCatalog;
use App\Support\Tenant\TenantNavigationCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route as RouteFacade;

class SyncIntegrationNavigationItems extends Command
{
    protected $signature = 'navigation:sync-integration-items
                            {--all-tenants : Run inside each tenant database}
                            {--tenants=* : Tenant id(s) when using --all-tenants}';

    protected $description = 'Insert integration-gated navigation items from config into existing menus.';

    public function handle(): int
    {
        if ($this->option('all-tenants')) {
            $tenantIds = array_values(array_filter((array) $this->option('tenants')));
            $forTenants = $tenantIds !== [] ? $tenantIds : null;
            $failed = false;

            tenancy()->runForMultiple($forTenants, function () use (&$failed): void {
                $label = tenancy()->tenant?->getTenantKey() ?? '?';
                $this->line("--- Tenant {$label} ---");
                if ($this->syncCurrentTenant() === self::FAILURE) {
                    $failed = true;
                }
            });

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        return $this->syncCurrentTenant();
    }

    private function syncCurrentTenant(): int
    {
        $integrationRoutes = collect(TenantNavigationCatalog::flattened())
            ->filter(fn (array $entry) => filled($entry['requires_integration'] ?? null))
            ->values();

        if ($integrationRoutes->isEmpty()) {
            $this->info('No integration-gated navigation routes in config.');

            return self::SUCCESS;
        }

        $menus = NavigationMenu::query()->get();
        $inserted = 0;

        foreach ($menus as $menu) {
            foreach ($integrationRoutes as $entry) {
                $route = $entry['route'];
                if ($route === null || ! RouteFacade::has($route)) {
                    continue;
                }

                $exists = NavigationMenuItem::query()
                    ->where('navigation_menu_id', $menu->id)
                    ->where('route_name', $route)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $parentId = $this->resolveParentId($menu->id, $entry['group_path']);

                $maxSort = NavigationMenuItem::query()
                    ->where('navigation_menu_id', $menu->id)
                    ->where('parent_id', $parentId)
                    ->max('sort_order');

                NavigationMenuItem::query()->create([
                    'navigation_menu_id' => $menu->id,
                    'parent_id' => $parentId,
                    'label' => $entry['label'],
                    'route_name' => $route,
                    'permission_key' => $entry['permission_key'],
                    'requires_integration' => $entry['requires_integration'],
                    'sort_order' => ($maxSort ?? -1) + 1,
                ]);

                $inserted++;
            }
        }

        if ($inserted > 0) {
            TenantNavigationCache::bumpVersion();
        }

        $this->info("Inserted {$inserted} integration navigation item(s).");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $groupPath
     */
    private function resolveParentId(int $menuId, array $groupPath): ?int
    {
        $parentId = null;

        foreach ($groupPath as $segment) {
            $parent = NavigationMenuItem::query()
                ->where('navigation_menu_id', $menuId)
                ->where('parent_id', $parentId)
                ->where('label', $segment)
                ->first();

            if ($parent === null) {
                break;
            }

            $parentId = $parent->id;
        }

        return $parentId;
    }
}
