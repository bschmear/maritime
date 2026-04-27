<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_make_model_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boat_make_id');
            $table->string('model_slug', 120);
            $table->string('model_label', 255);
            $table->string('status', 32);
            $table->string('catalog_asset_key', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('boat_make_id')->references('id')->on('boat_make')->cascadeOnDelete();
            $table->index(['boat_make_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_make_model_imports');
    }
};
