<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Role\Models\Role;
use App\Services\PermissionGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NavigationMenuPermissionsTest extends TestCase
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
    }

    public function test_navigationmenu_permissions_are_admin_only_by_default(): void
    {
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
