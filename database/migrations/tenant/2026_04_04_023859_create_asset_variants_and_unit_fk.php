<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('display_name')->nullable();
            $table->timestamps();

            $table->index('asset_id');
        });

        Schema::table('asset_units', function (Blueprint $table) {
            $table->foreignId('asset_variant_id')
                ->nullable()
                ->after('asset_id')
                ->constrained('asset_variants')
                ->restrictOnDelete();
            $table->index('asset_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('asset_units', function (Blueprint $table) {
            $table->dropForeign(['asset_variant_id']);
        });

        Schema::dropIfExists('asset_variants');
    }
};
