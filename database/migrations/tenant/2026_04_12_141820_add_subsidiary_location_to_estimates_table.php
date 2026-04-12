<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->foreignId('subsidiary_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('subsidiaries')
                ->nullOnDelete();

            $table->foreignId('location_id')
                ->nullable()
                ->after('subsidiary_id')
                ->constrained('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropForeign(['subsidiary_id']);
            $table->dropForeign(['location_id']);
            $table->dropColumn(['subsidiary_id', 'location_id']);
        });
    }
};
