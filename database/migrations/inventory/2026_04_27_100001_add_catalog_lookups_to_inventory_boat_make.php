<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional catalog links on inventory boat_make (runs after boat_type / hull_* tables exist).
 */
return new class extends Migration
{
    protected $connection = 'inventory';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('boat_make')) {
            return;
        }

        if (! $schema->hasTable('boat_type') || ! $schema->hasTable('hull_type') || ! $schema->hasTable('hull_material')) {
            return;
        }

        if ($schema->hasColumn('boat_make', 'boat_type_id')) {
            return;
        }

        $schema->table('boat_make', function (Blueprint $table) {
            $table->foreignId('boat_type_id')
                ->nullable()
                ->constrained('boat_type')
                ->nullOnDelete();
            $table->foreignId('hull_type_id')
                ->nullable()
                ->constrained('hull_type')
                ->nullOnDelete();
            $table->foreignId('hull_material_id')
                ->nullable()
                ->constrained('hull_material')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('boat_make') || ! $schema->hasColumn('boat_make', 'boat_type_id')) {
            return;
        }

        $schema->table('boat_make', function (Blueprint $table) {
            $table->dropForeign(['boat_type_id']);
            $table->dropForeign(['hull_type_id']);
            $table->dropForeign(['hull_material_id']);
            $table->dropColumn(['boat_type_id', 'hull_type_id', 'hull_material_id']);
        });
    }
};
