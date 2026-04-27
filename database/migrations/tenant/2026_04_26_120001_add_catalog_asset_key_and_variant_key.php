<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('catalog_asset_key')->nullable()->after('slug');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->unique(['make_id', 'catalog_asset_key'], 'assets_make_catalog_asset_key_unique');
        });

        Schema::table('asset_variants', function (Blueprint $table) {
            $table->string('key')->nullable()->after('display_name');
        });

        Schema::table('asset_variants', function (Blueprint $table) {
            $table->unique(['asset_id', 'key'], 'asset_variants_asset_id_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('asset_variants', function (Blueprint $table) {
            $table->dropUnique('asset_variants_asset_id_key_unique');
            $table->dropColumn('key');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropUnique('assets_make_catalog_asset_key_unique');
            $table->dropColumn('catalog_asset_key');
        });
    }
};
