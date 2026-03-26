<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use App\Domain\AssetSpec\Models\SpecGroup;
use App\Domain\AssetSpec\Support\AvailableAssetSpecsCache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AssetSpecDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed default groups
        $defaultGroups = [
            ['key' => 'dimensions', 'name' => 'Dimensions', 'position' => 1],
            ['key' => 'capacity', 'name' => 'Capacity', 'position' => 2],
            ['key' => 'engine', 'name' => 'Engine', 'position' => 3],
            ['key' => 'weight', 'name' => 'Weight', 'position' => 4],
            ['key' => 'performance', 'name' => 'Performance', 'position' => 5],
            ['key' => 'compliance', 'name' => 'Compliance', 'position' => 6],
            ['key' => 'features', 'name' => 'Features', 'position' => 7],
        ];

        foreach ($defaultGroups as $group) {
            SpecGroup::updateOrCreate(
                ['key' => $group['key']],
                $group
            );
        }

        // 2. Load JSON
        $jsonPath = app_path('Domain/AssetSpec/Schema/default_specs.json');

        if (!File::exists($jsonPath)) {
            $this->command?->error("Default specs file not found at: {$jsonPath}");
            return;
        }

        $data = json_decode(File::get($jsonPath), true);

        // 3. Map groups + seed definitions
        foreach ($data['fields'] as $field) {
            $groupKey = $field['group'] ?? null;
            $groupIdFromJson = $field['group_id'] ?? null;
            unset($field['group'], $field['group_id']);

            $group = null;
            if ($groupIdFromJson) {
                $group = SpecGroup::find($groupIdFromJson);
            }
            if (! $group && $groupKey) {
                $group = SpecGroup::where('key', $groupKey)->first();
            }

            if ($group) {
                $field['group_id'] = $group->id;
            }

            // Optional: ensure defaults
            $field['is_visible'] = $field['is_visible'] ?? true;

            AssetSpecDefinition::updateOrCreate(
                ['key' => $field['key']],
                $field
            );
        }

        AvailableAssetSpecsCache::forgetAll();

        $this->command?->info('Spec groups + asset spec definitions seeded successfully!');
    }
}
