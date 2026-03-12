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
        Schema::create('estimate_line_item_addon', function (Blueprint $table) {
            $table->id();

            $table->foreignId('estimate_line_item_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('addon_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('name')->nullable(); // For custom typed-in add-ons
            $table->decimal('price', 12, 2)->nullable(); // Override price or set for custom
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // e.g., color, electronics, other configs

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_line_item_addon');
    }
};
