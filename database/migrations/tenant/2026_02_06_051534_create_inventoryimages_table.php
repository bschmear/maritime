<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_images', function (Blueprint $table) {
            $table->id();

            $table->morphs('imageable');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('role')->nullable();
            $table->boolean('is_primary')->default(false);

            $table->string('display_name')->nullable();
            $table->text('description')->nullable();

            $table->string('file')->nullable();
            $table->string('file_extension', 10)->nullable();
            $table->integer('file_size')->default(0);

            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();

            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by_id')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_images');
    }
};
