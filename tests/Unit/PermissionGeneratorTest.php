<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Permission\Models\Permission;
use App\Domain\Role\Models\Role;
use App\Enums\RecordType;
use App\Services\PermissionGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PermissionGeneratorTest extends TestCase
{
    private const RESTRICTED_DOMAINS = ['financing', 'bill', 'billpayment'];

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

        Schema::connection('tenant')->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('domain');
            $table->string('action');
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
        });
    }

    public function test_sync_creates_four_permissions_per_record_type(): void
    {
        $generator = new PermissionGenerator;
        $stats = $generator->sync();

        $expected = count(RecordType::cases()) * 4;
        $this->assertSame($expected, $stats['created']);
        $this->assertSame(0, $stats['existing']);
        $this->assertSame($expected, Permission::query()->count());
    }

    public function test_sync_is_idempotent(): void
    {
        $generator = new PermissionGenerator;
        $generator->sync();
        $stats = $generator->sync();

        $expected = count(RecordType::cases()) * 4;
        $this->assertSame(0, $stats['created']);
        $this->assertSame($expected, $stats['existing']);
        $this->assertSame($expected, Permission::query()->count());
    }

    public function test_assign_default_role_permissions_matches_policy(): void
    {
        foreach (['admin', 'manager', 'employee', 'guest'] as $slug) {
            Role::query()->create([
                'display_name' => ucfirst($slug),
                'slug' => $slug,
                'description' => null,
            ]);
        }

        $generator = new PermissionGenerator;
        $generator->sync();
        $generator->assignDefaultRolePermissions();

        $total = Permission::query()->count();
        $restrictedCount = count(self::RESTRICTED_DOMAINS) * 4;

        $admin = Role::query()->where('slug', 'admin')->first();
        $this->assertSame($total, $admin->permissions()->count());
        $this->assertTrue($admin->hasPermission('financing.view'));
        $this->assertTrue($admin->hasPermission('bill.view'));
        $this->assertTrue($admin->hasPermission('billpayment.create'));
        $this->assertTrue($admin->hasPermission('navigationmenu.edit'));

        $manager = Role::query()->where('slug', 'manager')->first();
        $this->assertFalse($manager->hasPermission('user.create'));
        $this->assertFalse($manager->hasPermission('user.delete'));
        $this->assertFalse($manager->hasPermission('navigationmenu.edit'));
        $this->assertTrue($manager->hasPermission('user.view'));
        $this->assertTrue($manager->hasPermission('financing.view'));
        $this->assertTrue($manager->hasPermission('bill.edit'));
        $this->assertTrue($manager->hasPermission('billpayment.delete'));
        $this->assertSame($total - 6, $manager->permissions()->count());

        $employee = Role::query()->where('slug', 'employee')->first();
        $this->assertTrue($employee->hasPermission('invoice.view'));
        $this->assertTrue($employee->hasPermission('invoice.edit'));
        $this->assertFalse($employee->hasPermission('invoice.create'));
        $this->assertFalse($employee->hasPermission('financing.view'));
        $this->assertFalse($employee->hasPermission('bill.view'));
        $this->assertFalse($employee->hasPermission('billpayment.view'));
        $this->assertFalse($employee->hasPermission('navigationmenu.view'));
        $this->assertSame((count(RecordType::cases()) * 2) - ($restrictedCount / 4 * 2) - 2, $employee->permissions()->count());

        $guest = Role::query()->where('slug', 'guest')->first();
        $this->assertTrue($guest->hasPermission('invoice.view'));
        $this->assertFalse($guest->hasPermission('invoice.edit'));
        $this->assertFalse($guest->hasPermission('financing.view'));
        $this->assertFalse($guest->hasPermission('bill.view'));
        $this->assertFalse($guest->hasPermission('billpayment.view'));
        $this->assertFalse($guest->hasPermission('navigationmenu.view'));
        $this->assertSame(count(RecordType::cases()) - count(self::RESTRICTED_DOMAINS) - 1, $guest->permissions()->count());
    }
}
