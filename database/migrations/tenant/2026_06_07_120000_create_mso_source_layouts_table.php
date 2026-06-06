<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mso_source_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_document_id')
                ->unique()
                ->constrained('documents')
                ->cascadeOnDelete();
            $table->json('layout');
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mso_source_layouts');
    }
};
