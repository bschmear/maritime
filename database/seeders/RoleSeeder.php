<?php

namespace Database\Seeders;

use App\Domain\Role\Models\Role;
use App\Services\PermissionGenerator;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'display_name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full system access with all permissions',
            ],
            [
                'display_name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Can manage team members and most data',
            ],
            [
                'display_name' => 'Employee',
                'slug' => 'employee',
                'description' => 'Standard user with basic access',
            ],
            [
                'display_name' => 'Guest',
                'slug' => 'guest',
                'description' => 'Limited read-only access',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }

        $generator = app(PermissionGenerator::class);
        $generator->sync();
        $generator->assignDefaultRolePermissions();
    }
}
