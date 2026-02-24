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
        Schema::create('delivery_checklist_template_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_checklist_template_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('label');

            $table->string('category');
            // pre_delivery | upon_delivery

            $table->boolean('is_required')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_checklist_template_items');
    }
};
