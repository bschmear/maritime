<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentables', function (Blueprint $table) {
            $table->id();

            // Document being attached
            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();

            // Polymorphic owner
            $table->unsignedBigInteger('documentable_id');
            $table->string('documentable_type');

            // Optional metadata
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('role')->nullable(); // e.g. contract, invoice, disclosure

            $table->timestamps();

            // Prevent duplicate attachments
            $table->unique(
                ['document_id', 'documentable_id', 'documentable_type'],
                'documentables_unique'
            );

            // Performance indexes
            $table->index(['documentable_id', 'documentable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentables');
    }
};
