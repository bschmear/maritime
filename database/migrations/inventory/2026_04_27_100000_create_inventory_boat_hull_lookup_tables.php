<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reference lists for catalog / inventory (slug + label from Domain/BoatMake/Schema/*.json).
 */
return new class extends Migration
{
    protected $connection = 'inventory';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('boat_type')) {
            $schema->create('boat_type', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('display_name');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('hull_type')) {
            $schema->create('hull_type', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('display_name');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('hull_material')) {
            $schema->create('hull_material', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('display_name');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        $this->seedFromSlugDisplayJson('boat_types.json', 'boat_type');
        $this->seedFromSlugDisplayJson('hull_types.json', 'hull_type');
        $this->seedFromSlugDisplayJson('hull_materials.json', 'hull_material');
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->dropIfExists('hull_material');
        $schema->dropIfExists('hull_type');
        $schema->dropIfExists('boat_type');
    }

    /**
     * @param  array<string, string>  $map
     * @return list<array{slug: string, display_name: string, active: bool, created_at: \Illuminate\Support\Carbon, updated_at: \Illuminate\Support\Carbon}>
     */
    private function mapToRows(array $map): array
    {
        $now = now();
        $rows = [];
        foreach ($map as $slug => $displayName) {
            if (! is_string($slug) || $slug === '' || ! is_string($displayName)) {
                continue;
            }
            $rows[] = [
                'slug' => $slug,
                'display_name' => $displayName,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    private function seedFromSlugDisplayJson(string $file, string $table): void
    {
        $path = base_path('app/Domain/BoatMake/Schema/'.$file);
        if (! is_readable($path)) {
            throw new RuntimeException("Missing or unreadable schema file: {$path}");
        }

        /** @var array<string, string> $map */
        $map = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($map)) {
            throw new RuntimeException("Invalid JSON in {$path}");
        }

        $rows = $this->mapToRows($map);
        if ($rows === []) {
            return;
        }

        DB::connection($this->connection)->table($table)->upsert(
            $rows,
            ['slug'],
            ['display_name', 'active', 'updated_at']
        );
    }
};
