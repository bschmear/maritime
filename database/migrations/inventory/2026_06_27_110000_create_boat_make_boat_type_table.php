<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'inventory';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (
            ! $schema->hasTable('boat_make')
            || ! $schema->hasTable('boat_type')
            || $schema->hasTable('boat_make_boat_type')
        ) {
            return;
        }

        $schema->create('boat_make_boat_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boat_make_id')->constrained('boat_make')->cascadeOnDelete();
            $table->foreignId('boat_type_id')->constrained('boat_type')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['boat_make_id', 'boat_type_id']);
            $table->index(['boat_type_id', 'boat_make_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('boat_make_boat_type');
    }
};
