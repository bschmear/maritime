<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Permission\Models\Permission;
use App\Domain\Role\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RolePermissionTest extends TestCase
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
        \Illuminate\Support\Facades\DB::purge('tenant');

        Schema::connection('tenant')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::connection('tenant')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->unsignedBigInteger('current_role')->nullable();
            $table->boolean('is_technician')->default(false);
            $table->boolean('delivery_in_progress')->default(false);
            $table->foreign('current_role')->references('id')->on('roles')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function test_user_has_permission_via_role_pivot(): void
    {
        $role = Role::query()->create([
            'display_name' => 'Manager',
            'slug' => 'manager',
            'description' => null,
        ]);

        $permission = Permission::query()->create([
            'key' => 'invoice.edit',
            'domain' => 'invoice',
            'action' => 'edit',
            'label' => 'Invoice Edit',
        ]);

        $role->permissions()->sync([$permission->id]);

        $user = User::withoutEvents(function () use ($role) {
            return User::query()->create([
                'display_name' => 'Jane',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'current_role' => $role->id,
            ]);
        });

        $user->load('role.permissions');

        $this->assertTrue($user->hasPermission('invoice.edit'));
        $this->assertFalse($user->hasPermission('invoice.delete'));
    }

    public function test_user_without_role_has_no_permissions(): void
    {
        $user = User::withoutEvents(function () {
            return User::query()->create([
                'display_name' => 'Bob',
                'first_name' => 'Bob',
                'last_name' => 'Smith',
                'email' => 'bob@example.com',
                'current_role' => null,
            ]);
        });

        $this->assertFalse($user->hasPermission('invoice.edit'));
    }
}
