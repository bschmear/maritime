<?php

namespace Database\Seeders;

use App\Models\KioskRole;
use Illuminate\Database\Seeder;

class KioskRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Full access to all kiosk features including user management',
                'permissions' => [
                    'manage_users',
                    'manage_posts',
                    'manage_categories',
                    'manage_tags',
                    'manage_faqs',
                    'publish_posts',
                    'delete_posts',
                ],
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can create and edit content but cannot manage users',
                'permissions' => [
                    'manage_posts',
                    'manage_categories',
                    'manage_tags',
                    'manage_faqs',
                    'publish_posts',
                ],
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'description' => 'Can create and edit own posts',
                'permissions' => [
                    'create_posts',
                    'edit_own_posts',
                ],
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only access to kiosk dashboard',
                'permissions' => [
                    'view_dashboard',
                ],
            ],
        ];

        foreach ($roles as $role) {
            KioskRole::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
