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

        if (! $schema->hasTable('boat_make') || $schema->hasColumn('boat_make', 'logo_url')) {
            return;
        }

        $schema->table('boat_make', function (Blueprint $table) {
            $table->string('logo_url', 512)->nullable()->after('active');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('boat_make') || ! $schema->hasColumn('boat_make', 'logo_url')) {
            return;
        }

        $schema->table('boat_make', function (Blueprint $table) {
            $table->dropColumn('logo_url');
        });
    }
};
