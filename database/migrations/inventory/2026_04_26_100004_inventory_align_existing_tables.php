<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For databases that already had assets / asset_variants from manual DDL:
 * add boat_make FK and helpful unique indexes when missing.
 */
return new class extends Migration
{
    protected $connection = 'inventory';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('assets')) {
            if (! $schema->hasColumn('assets', 'attributes')) {
                $schema->table('assets', function (Blueprint $table) {
                    $table->json('attributes')->nullable();
                });
            }
            if (! $schema->hasColumn('assets', 'has_variants')) {
                $schema->table('assets', function (Blueprint $table) {
                    $table->boolean('has_variants')->default(false);
                });
            }
            // Unique (make_id, slug) for catalog dedupe — ignore if already present
            try {
                $schema->table('assets', function (Blueprint $table) {
                    $table->unique(['make_id', 'slug'], 'inventory_assets_make_slug_unique');
                });
            } catch (\Throwable) {
                // index may already exist or slug nullable duplicates prevented
            }
        }

        if ($schema->hasTable('asset_variants') && ! $schema->hasColumn('asset_variants', 'key')) {
            $schema->table('asset_variants', function (Blueprint $table) {
                $table->string('key')->nullable()->after('display_name');
            });
        }

        if ($schema->hasTable('asset_variants')) {
            try {
                $schema->table('asset_variants', function (Blueprint $table) {
                    $table->unique(['asset_id', 'key'], 'inventory_asset_variants_asset_key_unique');
                });
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        // non-reversible alignment migration
    }
};
