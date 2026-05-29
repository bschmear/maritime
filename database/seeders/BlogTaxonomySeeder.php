<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BlogTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasTable('tags')) {
            return;
        }

        $now = now();

        if (! Category::query()->exists()) {
            foreach ($this->categories() as $row) {
                Category::query()->create([
                    ...$row,
                    'slug' => Str::slug($row['slug'] ?? $row['name']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (! Tag::query()->exists()) {
            foreach ($this->tags() as $row) {
                Tag::query()->create([
                    ...$row,
                    'slug' => Str::slug($row['slug'] ?? $row['name']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * @return list<array{name: string, slug?: string, description: string|null}>
     */
    private function categories(): array
    {
        return [
            [
                'name' => 'Product updates',
                'slug' => 'product-updates',
                'description' => 'New features, improvements, and release notes for Helmful.',
            ],
            [
                'name' => 'How-to guides',
                'slug' => 'how-to-guides',
                'description' => 'Practical tips for running your dealership on Helmful.',
            ],
            [
                'name' => 'Industry insights',
                'slug' => 'industry-insights',
                'description' => 'Trends and ideas for marine retail, service, and operations.',
            ],
            [
                'name' => 'Company news',
                'slug' => 'company-news',
                'description' => 'Announcements and stories from the Helmful team.',
            ],
        ];
    }

    /**
     * @return list<array{name: string, slug?: string}>
     */
    private function tags(): array
    {
        return [
            ['name' => 'Helmful', 'slug' => 'helmful'],
            ['name' => 'Boat shows', 'slug' => 'boat-shows'],
            ['name' => 'Service department', 'slug' => 'service-department'],
            ['name' => 'Sales', 'slug' => 'sales'],
            ['name' => 'Inventory', 'slug' => 'inventory'],
            ['name' => 'Team management', 'slug' => 'team-management'],
            ['name' => 'QuickBooks', 'slug' => 'quickbooks'],
            ['name' => 'Payments', 'slug' => 'payments'],
            ['name' => 'Customer experience', 'slug' => 'customer-experience'],
            ['name' => 'Dealership tips', 'slug' => 'dealership-tips'],
        ];
    }
}
