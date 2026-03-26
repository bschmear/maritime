<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\AssetSpec\Models\AssetSpecDefinition;
use Illuminate\Support\Facades\File;

class AssetSpecDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = app_path('Domain/AssetSpec/Schema/default_specs.json');

        if (!File::exists($jsonPath)) {
            $this->command?->error("Default specs file not found at: {$jsonPath}");
            return;
        }

        $data = json_decode(File::get($jsonPath), true);

        foreach ($data['fields'] as $field) {
            AssetSpecDefinition::updateOrCreate(
                ['key' => $field['key']],
                $field
            );
        }

        $this->command?->info('Asset spec definitions seeded successfully!');
    }
}