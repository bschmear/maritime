<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('estimates', 'primary_version_id')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->foreignId('primary_version_id')
                    ->nullable()
                    ->after('terms')
                    ->constrained('estimate_versions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('estimates', 'primary_version_id')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->dropForeign(['primary_version_id']);
            });
        }
    }
};
