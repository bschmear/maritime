<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\NavigationMenu\Models\NavigationMenu;
use App\Domain\NavigationMenu\Models\NavigationMenuItem;
use App\Domain\Role\Models\Role;
use App\Services\TenantNavigation\NavigationMenuSyncService;
use App\Services\TenantNavigation\TenantNavigationResolver;
use App\Support\Tenant\TenantNavigationCache;
use App\Tenancy\CurrentTenantProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class NavigationMenuWorkspaceDefaultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
            'cache.stores.redis' => [
                'driver' => 'array',
                'serialize' => false,
            ],
        ]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('navigation_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('role_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('navigation_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('navigation_menu_id');
            $table->foreignId('parent_id')->nullable();
            $table->string('label');
            $table->string('route_name')->nullable();
            $table->string('permission_key')->nullable();
            $table->string('requires_integration')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function test_workspace_default_overrides_application_default(): void
    {
        $workspaceDefault = NavigationMenu::query()->create([
            'name' => 'Workspace default',
            'is_default' => true,
        ]);

        NavigationMenuItem::query()->create([
            'navigation_menu_id' => $workspaceDefault->id,
            'label' => 'Workspace Link',
            'route_name' => 'dashboard',
            'sort_order' => 0,
        ]);

        $profile = Mockery::mock(CurrentTenantProfile::class);
        $profile->shouldReceive('hasPermission')->andReturnTrue();

        $resolver = new TenantNavigationResolver($profile);

        $nav = $resolver->resolve(null);

        $this->assertSame('Workspace Link', $nav[0]['name']);
        $this->assertSame('dashboard', $nav[0]['href']);
    }

    public function test_role_menu_overrides_workspace_default(): void
    {
        $managerRole = Role::query()->create([
            'display_name' => 'Manager',
            'slug' => 'manager',
        ]);

        $workspaceDefault = NavigationMenu::query()->create([
            'name' => 'Workspace default',
            'is_default' => true,
        ]);

        NavigationMenuItem::query()->create([
            'navigation_menu_id' => $workspaceDefault->id,
            'label' => 'Workspace Link',
            'route_name' => 'dashboard',
            'sort_order' => 0,
        ]);

        $roleMenu = NavigationMenu::query()->create([
            'name' => 'Manager menu',
            'role_id' => $managerRole->id,
            'is_default' => false,
        ]);

        NavigationMenuItem::query()->create([
            'navigation_menu_id' => $roleMenu->id,
            'label' => 'Manager Link',
            'route_name' => 'dashboard',
            'sort_order' => 0,
        ]);

        $profile = Mockery::mock(CurrentTenantProfile::class);
        $profile->shouldReceive('hasPermission')->andReturnTrue();

        $resolver = new TenantNavigationResolver($profile);

        $nav = $resolver->resolve('manager');

        $this->assertSame('Manager Link', $nav[0]['name']);
    }

    public function test_role_menu_is_seeded_from_workspace_default_when_present(): void
    {
        $workspaceDefault = NavigationMenu::query()->create([
            'name' => 'Workspace default',
            'is_default' => true,
        ]);

        NavigationMenuItem::query()->create([
            'navigation_menu_id' => $workspaceDefault->id,
            'label' => 'Workspace Link',
            'route_name' => 'dashboard',
            'sort_order' => 0,
        ]);

        $roleMenu = NavigationMenu::query()->create([
            'name' => 'Manager menu',
            'role_id' => 42,
            'is_default' => false,
        ]);

        TenantNavigationCache::bumpVersion();

        app(NavigationMenuSyncService::class)->syncFromActiveDefault($roleMenu);

        $items = NavigationMenuItem::query()
            ->where('navigation_menu_id', $roleMenu->id)
            ->get();

        $this->assertCount(1, $items);
        $this->assertSame('Workspace Link', $items->first()->label);
        $this->assertSame('dashboard', $items->first()->route_name);
    }
}
