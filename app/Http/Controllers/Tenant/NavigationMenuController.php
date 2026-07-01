<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\NavigationMenu\Models\NavigationMenu;
use App\Domain\Role\Models\Role;
use App\Services\TenantNavigation\NavigationMenuSyncService;
use App\Services\TenantNavigation\TenantDefaultNavigation;
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
                'hasWorkspaceDefault' => false,
            ]);
        }

        $workspaceDefault = $this->workspaceDefaultMenu();

        $menus = collect([
            [
                'id' => null,
                'name' => 'Application default',
                'is_default' => false,
                'is_file_default' => true,
                'is_workspace_default' => false,
                'role' => null,
                'edit_url' => route('navigation-menus.default'),
            ],
        ]);

        if ($workspaceDefault !== null) {
            $menus->push([
                'id' => $workspaceDefault->id,
                'name' => $workspaceDefault->name,
                'is_default' => true,
                'is_file_default' => false,
                'is_workspace_default' => true,
                'role' => null,
                'edit_url' => route('navigation-menus.edit', $workspaceDefault),
            ]);
        }

        $menus = $menus->merge(
            NavigationMenu::query()
                ->with('role:id,display_name,slug')
                ->whereNotNull('role_id')
                ->orderBy('name')
                ->get()
                ->map(fn (NavigationMenu $menu) => [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'is_default' => false,
                    'is_file_default' => false,
                    'is_workspace_default' => false,
                    'role' => $menu->role ? [
                        'id' => $menu->role->id,
                        'display_name' => $menu->role->display_name,
                        'slug' => $menu->role->slug,
                    ] : null,
                    'edit_url' => route('navigation-menus.edit', $menu),
                ]),
        )->values();

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
            'hasWorkspaceDefault' => $workspaceDefault !== null,
        ]);
    }

    public function showDefault(): Response
    {
        $this->authorizeManage();

        return Inertia::render('Tenant/NavigationMenu/Edit', [
            'menu' => [
                'id' => null,
                'name' => 'Application default',
                'is_default' => false,
                'is_file_default' => true,
                'is_workspace_default' => false,
                'role' => null,
            ],
            'items' => TenantDefaultNavigation::editorNodes(),
            'routeCatalog' => TenantNavigationCatalog::flattened(),
            'rolePermissionKeys' => [],
            'readOnly' => true,
        ]);
    }

    public function storeWorkspaceDefault(): RedirectResponse
    {
        $this->authorizeManage();

        if ($this->workspaceDefaultMenu() !== null) {
            throw ValidationException::withMessages([
                'menu' => ['A workspace default menu already exists.'],
            ]);
        }

        $menu = NavigationMenu::query()->create([
            'name' => 'Workspace default',
            'role_id' => null,
            'is_default' => true,
        ]);

        $this->syncService->syncFromDefaultFile($menu);

        return redirect()
            ->route('navigation-menus.edit', $menu)
            ->with('success', 'Workspace default menu created from the application default.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id', 'unique:navigation_menus,role_id'],
        ]);

        $role = Role::query()->findOrFail($validated['role_id']);

        $menu = NavigationMenu::query()->create([
            'name' => $role->display_name.' menu',
            'role_id' => $role->id,
            'is_default' => false,
        ]);

        $this->syncService->syncFromActiveDefault($menu);

        return redirect()
            ->route('navigation-menus.edit', $menu)
            ->with('success', 'Role menu created from the active default menu.');
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
                'is_file_default' => false,
                'is_workspace_default' => $navigationMenu->is_default && $navigationMenu->role_id === null,
                'role' => $navigationMenu->role ? [
                    'id' => $navigationMenu->role->id,
                    'display_name' => $navigationMenu->role->display_name,
                    'slug' => $navigationMenu->role->slug,
                ] : null,
            ],
            'items' => $this->resolver->editorTree($navigationMenu),
            'routeCatalog' => TenantNavigationCatalog::flattened(),
            'rolePermissionKeys' => $this->rolePermissionKeysForMenu($navigationMenu),
            'readOnly' => false,
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

        $isWorkspaceDefault = $navigationMenu->is_default && $navigationMenu->role_id === null;

        $navigationMenu->delete();
        TenantNavigationCache::bumpVersion();

        $message = $isWorkspaceDefault
            ? 'Workspace default menu removed. Navigation will use the application default.'
            : 'Role menu deleted.';

        return redirect()
            ->route('navigation-menus.index')
            ->with('success', $message);
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

    private function workspaceDefaultMenu(): ?NavigationMenu
    {
        return NavigationMenu::query()
            ->where('is_default', true)
            ->whereNull('role_id')
            ->first();
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
