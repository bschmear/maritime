<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique('role_id');
            $table->index('is_default');
        });

        Schema::create('navigation_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('navigation_menu_id')
                ->constrained('navigation_menus')
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('navigation_menu_items')
                ->cascadeOnDelete();
            $table->string('label');
            $table->string('route_name')->nullable();
            $table->string('permission_key')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['navigation_menu_id', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_menu_items');
        Schema::dropIfExists('navigation_menus');
    }
};
