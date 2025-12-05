<?php

namespace Database\Seeders;

use Domain\Role\Models\Role;
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
                'permissions' => [

                ],
            ],
            [
                'display_name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Can manage team members and most data',
                'permissions' => [

                ],
            ],
            [
                'display_name' => 'User',
                'slug' => 'user',
                'description' => 'Standard user with basic access',
                'permissions' => [

                ],
            ],
            [
                'display_name' => 'Guest',
                'slug' => 'guest',
                'description' => 'Limited read-only access',
                'permissions' => [

                ],
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']], 
                $role 
            );
        }
    }
}