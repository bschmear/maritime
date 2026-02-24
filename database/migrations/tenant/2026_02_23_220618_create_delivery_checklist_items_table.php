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
        Schema::create('delivery_checklist_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('template_item_id')
                  ->nullable()
                  ->constrained('delivery_checklist_template_items')
                  ->nullOnDelete();

            $table->string('label');

            $table->string('category');
            // snapshot of template category

            $table->boolean('is_required')->default(false);

            $table->boolean('completed')->default(false);

            $table->text('notes')->nullable();

            $table->string('photo_path')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->foreignId('completed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['delivery_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_checklist_items');
    }
};
