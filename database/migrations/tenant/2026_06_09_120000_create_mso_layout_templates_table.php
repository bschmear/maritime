<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mso_layout_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('layout');
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mso_layout_templates');
    }
};
