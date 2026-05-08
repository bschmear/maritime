<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For databases that already had assets / asset_variants from manual DDL:
 * add missing columns and indexes. Prefer fresh migrate for inventory when schema diverges.
 */
return new class extends Migration
{
    protected $connection = 'inventory';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('assets')) {
            $this->addJsonOrBooleanColumnsToAssets($schema);
            $this->addMetaSpecificationColumnsToTable($schema, 'assets');
            try {
                $schema->table('assets', function (Blueprint $table) {
                    $table->unique(['make_id', 'slug'], 'inventory_assets_make_slug_unique');
                });
            } catch (\Throwable) {
            }
        }

        if (! $schema->hasTable('asset_variants')) {
            return;
        }

        $this->addAssetVariantCoreColumns($schema);
        $this->addMetaSpecificationColumnsToTable($schema, 'asset_variants');
        $this->addJsonOrBooleanColumnsToAssetVariants($schema);

        try {
            $schema->table('asset_variants', function (Blueprint $table) {
                $table->unique(['asset_id', 'key'], 'inventory_asset_variants_asset_key_unique');
            });
        } catch (\Throwable) {
        }
    }

    private function addJsonOrBooleanColumnsToAssets(\Illuminate\Database\Schema\Builder $schema): void
    {
        if (! $schema->hasColumn('assets', 'attributes')) {
            $schema->table('assets', function (Blueprint $table) {
                $table->json('attributes')->nullable();
            });
        }
        if (! $schema->hasColumn('assets', 'catalog_data')) {
            $schema->table('assets', function (Blueprint $table) {
                $table->json('catalog_data')->nullable();
            });
        }
        if (! $schema->hasColumn('assets', 'features')) {
            $schema->table('assets', function (Blueprint $table) {
                $table->json('features')->nullable();
            });
        }
        if (! $schema->hasColumn('assets', 'has_variants')) {
            $schema->table('assets', function (Blueprint $table) {
                $table->boolean('has_variants')->default(false);
            });
        }
    }

    private function addJsonOrBooleanColumnsToAssetVariants(\Illuminate\Database\Schema\Builder $schema): void
    {
        if (! $schema->hasColumn('asset_variants', 'attributes')) {
            $schema->table('asset_variants', function (Blueprint $table) {
                $table->json('attributes')->nullable();
            });
        }
        if (! $schema->hasColumn('asset_variants', 'catalog_data')) {
            $schema->table('asset_variants', function (Blueprint $table) {
                $table->json('catalog_data')->nullable();
            });
        }
        if (! $schema->hasColumn('asset_variants', 'features')) {
            $schema->table('asset_variants', function (Blueprint $table) {
                $table->json('features')->nullable();
            });
        }
        if (! $schema->hasColumn('asset_variants', 'has_variants')) {
            $schema->table('asset_variants', function (Blueprint $table) {
                $table->boolean('has_variants')->default(false);
            });
        }
    }

    private function addAssetVariantCoreColumns(\Illuminate\Database\Schema\Builder $schema): void
    {
        $this->addColumnIfMissing($schema, 'asset_variants', 'inactive', function (Blueprint $table): void {
            $table->boolean('inactive')->default(false);
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'type', function (Blueprint $table): void {
            $table->unsignedTinyInteger('type')->default(1);
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'display_name', function (Blueprint $table): void {
            $table->string('display_name')->nullable();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'slug', function (Blueprint $table): void {
            $table->string('slug')->nullable();
        });
        if (! $schema->hasColumn('asset_variants', 'key')) {
            $schema->table('asset_variants', function (Blueprint $table) {
                $table->string('key')->nullable();
            });
        }
        $this->addColumnIfMissing($schema, 'asset_variants', 'name', function (Blueprint $table): void {
            $table->string('name')->nullable();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'make_id', function (Blueprint $table): void {
            $table->foreignId('make_id')->nullable()->constrained('boat_make')->nullOnDelete();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'model', function (Blueprint $table): void {
            $table->string('model')->nullable();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'year', function (Blueprint $table): void {
            $table->string('year')->nullable();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'engine_shaft', function (Blueprint $table): void {
            $table->string('engine_shaft')->nullable();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'water_tank', function (Blueprint $table): void {
            $table->string('water_tank')->nullable();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'category', function (Blueprint $table): void {
            $table->string('category')->nullable();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'engine_details', function (Blueprint $table): void {
            $table->text('engine_details')->nullable();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'description', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'default_cost', function (Blueprint $table): void {
            $table->decimal('default_cost', 12, 2)->nullable();
        });
        $this->addColumnIfMissing($schema, 'asset_variants', 'default_price', function (Blueprint $table): void {
            $table->decimal('default_price', 12, 2)->nullable();
        });
    }

    private function addMetaSpecificationColumnsToTable(\Illuminate\Database\Schema\Builder $schema, string $table): void
    {
        $this->addColumnIfMissing($schema, $table, 'length_mm', function (Blueprint $table): void {
            $table->unsignedInteger('length_mm')->nullable();
        });
        $this->addColumnIfMissing($schema, $table, 'width_mm', function (Blueprint $table): void {
            $table->unsignedInteger('width_mm')->nullable();
        });
        $this->addColumnIfMissing($schema, $table, 'height_mm', function (Blueprint $table): void {
            $table->unsignedInteger('height_mm')->nullable();
        });
        $this->addColumnIfMissing($schema, $table, 'weight_kg', function (Blueprint $table): void {
            $table->unsignedInteger('weight_kg')->nullable();
        });
        $this->addColumnIfMissing($schema, $table, 'capacity_persons', function (Blueprint $table): void {
            $table->unsignedInteger('capacity_persons')->nullable();
        });
        $this->addColumnIfMissing($schema, $table, 'max_hp', function (Blueprint $table): void {
            $table->unsignedInteger('max_hp')->nullable();
        });
        $this->addColumnIfMissing($schema, $table, 'fuel_capacity_l', function (Blueprint $table): void {
            $table->unsignedInteger('fuel_capacity_l')->nullable();
        });
    }

    /**
     * @param  callable(Blueprint): void  $callback
     */
    private function addColumnIfMissing(\Illuminate\Database\Schema\Builder $schema, string $table, string $column, callable $callback): void
    {
        if (! $schema->hasColumn($table, $column)) {
            $schema->table($table, $callback);
        }
    }

    public function down(): void
    {
        // non-reversible alignment migration
    }
};
