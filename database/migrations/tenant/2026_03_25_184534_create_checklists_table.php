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
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('checklist_template_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        
            // Polymorphic relation (transaction, delivery, etc.)
            $table->morphs('checklistable');
        
            $table->string('name');
        
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('checklist_id')
                ->constrained()
                ->cascadeOnDelete();
        
            $table->string('label');
        
            $table->boolean('required')->default(false);
        
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
        
            $table->integer('position')->default(0);
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('checklist_items');
    }
};
