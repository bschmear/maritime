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
        Schema::create('subsidiary_user', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('subsidiary_id')
                ->constrained()
                ->cascadeOnDelete();
        
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
        
            // Optional but very useful
            $table->boolean('primary')->default(false);
            $table->string('role')->nullable();
            $table->timestamps();
        
            $table->unique(['subsidiary_id', 'user_id']);
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subsidiary_user');
    }
};
