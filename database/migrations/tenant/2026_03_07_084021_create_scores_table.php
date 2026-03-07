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
        Schema::create('scores', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation (Lead or Contact)
            $table->morphs('scorable');

            // Ownership & responsibility
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_id')->nullable()->constrained('users')->nullOnDelete();

            // Score details
            $table->string('score_type')->default('manual')->index();
            $table->decimal('score_value', 5, 2)->default(0);
            $table->decimal('weight', 5, 2)->nullable();
            $table->json('meta')->nullable();
            $table->string('notes', 250)->nullable(); // max 250 chars
            $table->boolean('is_current')->default(true)->index();

            $table->timestamps();

            // Indexes for performance
            $table->index(['team_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
