<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('preferred_subsidiary_id')
                ->nullable()
                ->after('current_role')
                ->constrained('subsidiaries')
                ->nullOnDelete();
            $table->foreignId('preferred_location_id')
                ->nullable()
                ->after('preferred_subsidiary_id')
                ->constrained('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_location_id');
            $table->dropConstrainedForeignId('preferred_subsidiary_id');
        });
    }
};
