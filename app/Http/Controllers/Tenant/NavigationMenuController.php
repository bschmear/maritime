<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\NavigationMenu\Models\NavigationMenu;
use App\Domain\Role\Models\Role;
use App\Services\TenantNavigation\NavigationMenuSyncService;
use App\Services\TenantNavigation\TenantNavigationCatalog;
use App\Services\TenantNavigation\TenantNavigationResolver;
use App\Support\Tenant\TenantNavigationCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NavigationMenuController extends Controller
{
    public function __construct(
        private readonly TenantNavigationResolver $resolver,
        private readonly NavigationMenuSyncService $syncService,
    ) {
        $this->middleware('auth');
    }

    public function index(): Response
    {
        if (! $this->isAdministrator()) {
            return Inertia::render('Tenant/NavigationMenu/Index', [
                'canManage' => false,
                'menus' => [],
                'availableRoles' => [],
            ]);
        }

        $menus = NavigationMenu::query()
            ->with('role:id,display_name,slug')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (NavigationMenu $menu) => [
                'id' => $menu->id,
                'name' => $menu->name,
                'is_default' => $menu->is_default,
                'role' => $menu->role ? [
                    'id' => $menu->role->id,
                    'display_name' => $menu->role->display_name,
                    'slug' => $menu->role->slug,
                ] : null,
                'edit_url' => route('navigation-menus.edit', $menu),
            ]);

        $rolesWithMenus = NavigationMenu::query()
            ->whereNotNull('role_id')
            ->pluck('role_id')
            ->all();

        $availableRoles = Role::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'slug'])
            ->filter(fn (Role $role) => ! in_array($role->id, $rolesWithMenus, true))
            ->values();

        return Inertia::render('Tenant/NavigationMenu/Index', [
            'canManage' => true,
            'menus' => $menus,
            'availableRoles' => $availableRoles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id', 'unique:navigation_menus,role_id'],
        ]);

        $role = Role::query()->findOrFail($validated['role_id']);
        $defaultMenu = NavigationMenu::query()->where('is_default', true)->firstOrFail();

        $menu = NavigationMenu::query()->create([
            'name' => $role->display_name.' menu',
            'role_id' => $role->id,
            'is_default' => false,
        ]);

        $this->resolver->cloneMenuItems($defaultMenu, $menu);
        TenantNavigationCache::bumpVersion();

        return redirect()
            ->route('navigation-menus.edit', $menu)
            ->with('success', 'Role menu created from the default menu.');
    }

    public function edit(NavigationMenu $navigationMenu): Response
    {
        $this->authorizeManage();

        $navigationMenu->load('role:id,display_name,slug');

        return Inertia::render('Tenant/NavigationMenu/Edit', [
            'menu' => [
                'id' => $navigationMenu->id,
                'name' => $navigationMenu->name,
                'is_default' => $navigationMenu->is_default,
                'role' => $navigationMenu->role ? [
                    'id' => $navigationMenu->role->id,
                    'display_name' => $navigationMenu->role->display_name,
                    'slug' => $navigationMenu->role->slug,
                ] : null,
            ],
            'items' => $this->resolver->editorTree($navigationMenu),
            'routeCatalog' => TenantNavigationCatalog::flattened(),
            'rolePermissionKeys' => $this->rolePermissionKeysForMenu($navigationMenu),
        ]);
    }

    /**
     * @return list<string>
     */
    private function rolePermissionKeysForMenu(NavigationMenu $menu): array
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

    public function update(Request $request, NavigationMenu $navigationMenu): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.route_name' => ['nullable', 'string', 'max:255'],
            'items.*.children' => ['nullable', 'array'],
        ]);

        $this->validateRouteNames($validated['items']);

        $navigationMenu->update(['name' => $validated['name']]);
        $this->syncService->sync($navigationMenu, $validated['items']);

        return back()->with('success', 'Navigation menu saved.');
    }

    public function destroy(NavigationMenu $navigationMenu): RedirectResponse
    {
        $this->authorizeManage();

        if ($navigationMenu->is_default) {
            throw ValidationException::withMessages([
                'menu' => ['The default menu cannot be deleted.'],
            ]);
        }

        $navigationMenu->delete();
        TenantNavigationCache::bumpVersion();

        return redirect()
            ->route('navigation-menus.index')
            ->with('success', 'Role menu deleted.');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function validateRouteNames(array $items): void
    {
        foreach ($items as $item) {
            $route = $item['route_name'] ?? null;
            if ($route !== null && $route !== '' && ! RouteFacade::has($route)) {
                throw ValidationException::withMessages([
                    'items' => ["The route \"{$route}\" is not valid."],
                ]);
            }

            if (! empty($item['children'])) {
                $this->validateRouteNames($item['children']);
            }
        }
    }

    private function authorizeManage(): void
    {
        abort_unless($this->isAdministrator(), 403);
    }

    private function isAdministrator(): bool
    {
        return current_tenant_role_slug() === 'admin';
    }
}
