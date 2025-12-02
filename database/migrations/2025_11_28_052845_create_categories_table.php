<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->longText('description')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
