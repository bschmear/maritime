<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\NavigationMenu\Models\NavigationMenu;
use App\Domain\NavigationMenu\Models\NavigationMenuItem;
use App\Domain\Role\Models\Role;
use App\Services\PermissionGenerator;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NavigationMenuSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
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
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function test_seeder_creates_default_menu_from_config(): void
    {
        $this->seed(NavigationMenuSeeder::class);

        $menu = NavigationMenu::query()->where('is_default', true)->first();
        $this->assertNotNull($menu);
        $this->assertSame('Default', $menu->name);
        $this->assertGreaterThan(10, NavigationMenuItem::query()->where('navigation_menu_id', $menu->id)->count());

        $dashboard = NavigationMenuItem::query()
            ->where('navigation_menu_id', $menu->id)
            ->where('route_name', 'dashboard')
            ->first();

        $this->assertNotNull($dashboard);
        $this->assertSame('Overview', $dashboard->label);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(NavigationMenuSeeder::class);
        $count = NavigationMenu::query()->count();

        $this->seed(NavigationMenuSeeder::class);

        $this->assertSame($count, NavigationMenu::query()->count());
    }

    public function test_navigationmenu_permissions_are_admin_only_by_default(): void
    {
        Schema::connection('tenant')->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('domain');
            $table->string('action');
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id');
            $table->foreignId('permission_id');
            $table->unique(['role_id', 'permission_id']);
        });

        foreach (['admin', 'manager', 'employee', 'guest'] as $slug) {
            Role::query()->create([
                'display_name' => ucfirst($slug),
                'slug' => $slug,
            ]);
        }

        $generator = new PermissionGenerator;
        $generator->sync();
        $generator->assignDefaultRolePermissions();

        $admin = Role::query()->where('slug', 'admin')->firstOrFail();
        $manager = Role::query()->where('slug', 'manager')->firstOrFail();

        $this->assertTrue($admin->hasPermission('navigationmenu.edit'));
        $this->assertFalse($manager->hasPermission('navigationmenu.edit'));
    }
}
