<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Integration\Models\Integration;
use App\Domain\NavigationMenu\Models\NavigationMenu;
use App\Domain\NavigationMenu\Models\NavigationMenuItem;
use App\Domain\Role\Models\Role;
use App\Enums\Integration\IntegrationType;
use App\Services\TenantNavigation\TenantNavigationResolver;
use App\Support\Tenant\TenantNavigationCache;
use App\Tenancy\CurrentTenantProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TenantNavigationResolverTest extends TestCase
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

        Schema::connection('tenant')->create('integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('integration_type');
            $table->string('external_id')->nullable();
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->text('access_token')->nullable();
            $table->timestamps();
        });
    }

    public function test_role_menu_replaces_default_when_present(): void
    {
        $managerRole = Role::query()->create([
            'display_name' => 'Manager',
            'slug' => 'manager',
        ]);

        $defaultMenu = NavigationMenu::query()->create([
            'name' => 'Default',
            'is_default' => true,
        ]);

        NavigationMenuItem::query()->create([
            'navigation_menu_id' => $defaultMenu->id,
            'label' => 'Default Link',
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
            'route_name' => 'sales.index',
            'sort_order' => 0,
        ]);

        $profile = Mockery::mock(CurrentTenantProfile::class);
        $profile->shouldReceive('hasPermission')->andReturnTrue();

        $resolver = new TenantNavigationResolver($profile);

        $defaultNav = $resolver->resolve(null);
        $this->assertSame('Default Link', $defaultNav[0]['name']);

        $managerNav = $resolver->resolve('manager');
        $this->assertSame('Manager Link', $managerNav[0]['name']);
    }

    public function test_cache_version_bump_changes_resolved_key(): void
    {
        TenantNavigationCache::bumpVersion();

        $menu = NavigationMenu::query()->create([
            'name' => 'Default',
            'is_default' => true,
        ]);

        NavigationMenuItem::query()->create([
            'navigation_menu_id' => $menu->id,
            'label' => 'Overview',
            'route_name' => 'dashboard',
            'sort_order' => 0,
        ]);

        $profile = Mockery::mock(CurrentTenantProfile::class);
        $profile->shouldReceive('hasPermission')->andReturnTrue();

        $resolver = new TenantNavigationResolver($profile);

        $first = $resolver->resolve(null);
        TenantNavigationCache::bumpVersion();
        $second = $resolver->resolve(null);

        $this->assertSame($first, $second);
    }

    public function test_integration_gated_items_are_hidden_when_integration_inactive(): void
    {
        $menu = NavigationMenu::query()->create([
            'name' => 'Default',
            'is_default' => true,
        ]);

        NavigationMenuItem::query()->create([
            'navigation_menu_id' => $menu->id,
            'label' => 'Overview',
            'route_name' => 'dashboard',
            'sort_order' => 0,
        ]);

        NavigationMenuItem::query()->create([
            'navigation_menu_id' => $menu->id,
            'label' => 'Shipments',
            'route_name' => 'shipments.index',
            'requires_integration' => 'easypost',
            'sort_order' => 1,
        ]);

        $profile = Mockery::mock(CurrentTenantProfile::class);
        $profile->shouldReceive('hasPermission')->andReturnTrue();

        $resolver = new TenantNavigationResolver($profile);

        $hidden = $resolver->resolve(null);
        $this->assertCount(1, $hidden);
        $this->assertSame('Overview', $hidden[0]['name']);

        Integration::query()->create([
            'integration_type' => IntegrationType::EasyPost,
            'active' => true,
            'name' => 'EasyPost',
            'access_token' => 'EZAKTEST',
        ]);

        TenantNavigationCache::bumpVersion();

        $visible = $resolver->resolve(null);
        $this->assertCount(2, $visible);
        $this->assertSame('Shipments', $visible[1]['name']);
    }
}
