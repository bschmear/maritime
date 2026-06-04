<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('label');
            $table->string('route');
            $table->json('route_params')->nullable();
            $table->string('route_params_hash', 64);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'route', 'route_params_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorites');
    }
};
