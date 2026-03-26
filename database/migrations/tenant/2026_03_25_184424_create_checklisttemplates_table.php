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
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
        
            $table->string('name');
        
            // Where this template is used (transaction, delivery, contract, etc.)
            $table->string('context')->nullable()->index();
        
            $table->boolean('is_active')->default(true);
        
            $table->timestamps();
        });

        Schema::create('checklist_template_items', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('checklist_template_id')
                ->constrained()
                ->cascadeOnDelete();
        
            $table->string('label');
            $table->boolean('required')->default(false);
        
            $table->integer('position')->default(0);
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('checklist_template_items');
    }
};
